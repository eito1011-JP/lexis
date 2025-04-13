import { Router } from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import path from 'path';

const execAsync = promisify(exec);
const router = Router();

// プロジェクトのルートディレクトリを取得
const rootDir = path.resolve(process.cwd());

/**
 * 現在のブランチに変更があるか確認するエンドポイント
 */
router.get('/check-diff', async (req, res) => {
  try {
    // git status --porcelain で変更ファイルを確認
    const { stdout } = await execAsync('git status --porcelain', { cwd: rootDir });

    // 空でなければ変更あり
    const hasDiff = stdout.trim().length > 0;

    res.json({ success: true, hasDiff });
  } catch (error) {
    console.error('Git diff check error:', error);
    res.status(500).json({
      success: false,
      error: '変更状態の確認に失敗しました',
    });
  }
});

/**
 * 新しいブランチを作成するエンドポイント
 */
router.post('/create-branch', async (req, res) => {
  const { branchName, fromBranch = 'main' } = req.body;

  if (!branchName) {
    return res.status(400).json({
      success: false,
      error: 'ブランチ名は必須です',
    });
  }

  try {
    // 指定されたブランチ（通常はmain）に切り替え
    await execAsync(`git checkout ${fromBranch}`, { cwd: rootDir });

    // 最新の状態に更新
    await execAsync('git pull', { cwd: rootDir });

    // 新しいブランチを作成して切り替え
    await execAsync(`git checkout -b ${branchName}`, { cwd: rootDir });

    res.json({
      success: true,
      message: `ブランチ "${branchName}" を作成しました`,
    });
  } catch (error) {
    console.error('Branch creation error:', error);
    res.status(500).json({
      success: false,
      error: 'ブランチの作成に失敗しました',
    });
  }
});

/**
 * 現在のブランチからPull Requestを作成するエンドポイント
 */
router.post('/create-pr', async (req, res) => {
  const { title = '更新内容の提出', description = 'このPRはハンドブックの更新を含みます。' } =
    req.body;

  try {
    // 現在のブランチ名を取得
    const { stdout: branchStdout } = await execAsync('git branch --show-current', { cwd: rootDir });
    const currentBranch = branchStdout.trim();

    if (currentBranch === 'main' || currentBranch === 'master') {
      return res.status(400).json({
        success: false,
        error: 'メインブランチからのPRは作成できません',
      });
    }

    // 変更をコミット
    await execAsync('git add .', { cwd: rootDir });
    await execAsync(`git commit -m "${title}"`, { cwd: rootDir });

    // リモートにプッシュ
    await execAsync(`git push -u origin ${currentBranch}`, { cwd: rootDir });

    // GitHub CLI を使用してPRを作成 (GitHub CLIがインストールされていることが前提)
    try {
      await execAsync(
        `gh pr create --base main --head ${currentBranch} --title "${title}" --body "${description}"`,
        { cwd: rootDir }
      );
      res.json({
        success: true,
        message: 'Pull Requestが作成されました',
        pullRequest: {
          branch: currentBranch,
          title,
        },
      });
    } catch (prError) {
      console.error('PR作成エラー:', prError);
      // PRの作成に失敗しても、変更はプッシュされている
      res.status(202).json({
        success: true,
        partial: true,
        message:
          'コードの変更はプッシュされましたが、PRの作成に失敗しました。GitHubウェブサイトから手動でPRを作成してください。',
        branch: currentBranch,
      });
    }
  } catch (error) {
    console.error('Git PR creation error:', error);
    res.status(500).json({
      success: false,
      error: 'Pull Requestの作成に失敗しました',
    });
  }
});

export default router;
