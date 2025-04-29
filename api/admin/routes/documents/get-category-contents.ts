import express, { Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { sessionService } from '../../../../src/services/sessionService';
import fs from 'fs';
import path from 'path';
import matter from 'gray-matter';

// Request型の拡張
declare global {
  namespace Express {
    interface Request {
      user?: {
        userId: number;
        email: string;
      };
    }
  }
}

const router = express.Router();

/**
 * カテゴリコンテンツ取得API
 *
 * 指定されたカテゴリ内のファイルとサブカテゴリの一覧を取得します。
 *
 * リクエスト:
 * GET /api/admin/documents/category-contents?slug=<category-slug>
 *
 * レスポンス:
 * 成功: { items: Array<{ name: string, path: string, type: 'document' | 'category', label?: string, isDraft: boolean }> }
 * 失敗: { error: string }
 */
router.get('/category-contents', async (req: Request, res: Response) => {
  try {
    // クエリパラメータからスラッグを取得
    const { slug } = req.query;
    if (!slug || typeof slug !== 'string') {
      return res.status(400).json({
        error: 'A valid slug is required',
      });
    }

    // docsディレクトリのパス
    const docsDir = path.join(process.cwd(), 'docs');

    // docsディレクトリ内の各カテゴリを取得
    const dirItems = fs
      .readdirSync(docsDir, { withFileTypes: true })
      .filter(item => item.isDirectory());

    // スラッグに一致するカテゴリを探す
    let targetDir = '';
    const categories = dirItems.filter(item => item.isDirectory());

    let categoryPath = '';

    for (const category of categories) {
      const categoryName = category.name;
      const currentCategoryPath = path.join(docsDir, categoryName);

      // カテゴリ名がスラッグと一致するか確認
      if (categoryName === slug) {
        targetDir = currentCategoryPath;
        categoryPath = categoryName;
        break;
      }

      try {
        // カテゴリ内のMarkdownファイルを検索
        const files = fs.readdirSync(currentCategoryPath, { withFileTypes: true });
        const mdFiles = files.filter(file => file.isFile() && file.name.endsWith('.md'));

        // Markdownファイルを見つけた場合、front matterからスラッグを確認
        for (const mdFile of mdFiles) {
          const filePath = path.join(currentCategoryPath, mdFile.name);
          const fileContent = fs.readFileSync(filePath, 'utf8');
          const { data } = matter(fileContent);

          if (data.slug && data.slug.split('/')[0] === slug) {
            targetDir = currentCategoryPath;
            categoryPath = categoryName;
            break;
          }
        }
      } catch (err) {
        console.error(`${categoryName}内のファイル読み込みエラー:`, err);
      }
    }

    // 対象のカテゴリが見つからない場合
    if (!targetDir) {
      return res.status(404).json({
        error: 'Category not found',
      });
    }

    // カテゴリ内のファイルとサブカテゴリを取得
    const dirContents = fs.readdirSync(targetDir, { withFileTypes: true });

    // アイテム情報を生成
    const items = dirContents
      .map(item => {
        const itemPath = path.join(categoryPath, item.name);

        // タイプ（file or folder）を決定
        const type = item.isDirectory() ? 'folder' : 'file';

        // ドキュメントの場合はタイトルを取得
        let label = '';
        let isDraft = false;
        let lastEditedBy = '';
        if (type === 'file' && item.name.endsWith('.md')) {
          try {
            const filePath = path.join(targetDir, item.name);
            const fileContent = fs.readFileSync(filePath, 'utf8');
            const { data } = matter(fileContent);
            label = data.sidebar_label || data.label || item.name.replace('.md', '');
            isDraft = data.draft;
            lastEditedBy = data.last_edited_by || '';
          } catch (err) {
            console.error(`ファイル ${item.name} の読み込みエラー:`, err);
            label = item.name.replace('.md', '');
          }
        } else {
            const categoryJsonPath = path.join(targetDir, item.name, '_category.json');
            if (fs.existsSync(categoryJsonPath)) {
              const categoryJson = JSON.parse(fs.readFileSync(categoryJsonPath, 'utf8'));
              label = categoryJson.label;
            }
        }

        return {
          name: item.name,
          path: itemPath,
          type,
          ...(label && { label }),
          isDraft: isDraft,
          lastEditedBy: lastEditedBy,
        };
      })
      .filter(item => item.type === 'folder' || item.name.endsWith('.md'));

    // 成功レスポンス
    return res.status(200).json({
      items,
    });
  } catch (err) {
    console.error('カテゴリコンテンツ取得エラー:', err);
    return res.status(500).json({
      error: 'Failed to get category contents',
    });
  }
});

export const getCategoryContentsRouter = router;
