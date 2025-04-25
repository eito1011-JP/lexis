import { Router, Request, Response } from 'express';
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

const router = Router();

router.get('/*', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    // 認証チェック
    const loginUser = await sessionService.getSessionUser(sessionId);
    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    // パスからターゲットディレクトリを特定
    const requestPath = req.params[0] || '';
    const pathSegments = requestPath.split('/').filter(segment => segment.length > 0);

    const apiDir = path.dirname(path.dirname(path.dirname(path.dirname(__filename))));
    const rootDir = path.dirname(apiDir);
    const docsDir = path.join(rootDir, 'docs');

    // ブレッドクラムを構築
    const breadcrumbs = [];
    let currentPath = '';

    for (const segment of pathSegments) {
      currentPath = currentPath ? `${currentPath}/${segment}` : segment;
      breadcrumbs.push({
        name: segment,
        path: `/admin/documents/${currentPath}`,
      });
    }

    // ターゲットディレクトリのパスを構築
    const targetDir = path.join(docsDir, ...pathSegments);

    // ディレクトリが存在するか確認
    if (!fs.existsSync(targetDir)) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: 'Directory not found',
      });
    }

    // ディレクトリの内容を取得
    const dirContents = fs.readdirSync(targetDir, { withFileTypes: true });

    // ファイルとフォルダに分類して処理
    const contentItems = await Promise.all(
      dirContents.map(async item => {
        const itemPathSegments = [...pathSegments, item.name];

        if (item.isDirectory()) {
          // フォルダの場合（カテゴリとして処理）
          // カテゴリ情報を取得するためにカテゴリ内の_category.jsonファイルを探す
          const categoryMetadataPath = path.join(targetDir, item.name, '_category.json');

          let label = item.name;
          let position = 999; // デフォルト値
          let description = '';

          // カテゴリメタデータファイルが存在する場合、そこからメタデータを取得
          if (fs.existsSync(categoryMetadataPath)) {
            const categoryContent = fs.readFileSync(categoryMetadataPath, 'utf8');
            const data = JSON.parse(categoryContent);

            label = data.sidebar_label || data.label || item.name;
            position = data.position || position;
            description = data.link?.description || '';
          }

          return {
            type: 'category',
            slug: item.name,
            label,
            position,
            description,
          };
        } else if (
          item.isFile() &&
          item.name.endsWith('.md') &&
          item.name !== '_category.md' &&
          item.name !== '_category.json'
        ) {
          // マークダウンファイルの場合（ドキュメントとして処理）
          const filePath = path.join(targetDir, item.name);
          const fileContent = fs.readFileSync(filePath, 'utf8');

          // front-matterを解析
          const { data, content } = matter(fileContent);

          return {
            type: 'file',
            slug: data.slug || item.name.replace('.md', ''),
            label: data.sidebar_label || data.label || item.name.replace('.md', ''),
            position: data.position || 999,
            description: data.description || '',
            content: content,
          };
        }

        // その他のファイルは無視
        return null;
      })
    );

    // nullでない項目のみフィルタリング
    const validItems = contentItems.filter(item => item !== null);

    // アイテムを表示順でソート
    validItems.sort((a, b) => {
      if (a.type !== b.type) {
        // カテゴリを先に表示
        return a.type === 'category' ? -1 : 1;
      }
      // 同じタイプの場合はpositionでソート
      return (a.position || 999) - (b.position || 999);
    });

    return res.status(HTTP_STATUS.OK).json({
      items: validItems,
      breadcrumbs,
    });
  } catch (error) {
    console.error('フォルダコンテンツ取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getDocumentsRouter = router;
