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
 * カテゴリ一覧取得API
 *
 * docs配下の各カテゴリ（ディレクトリ）の情報を取得します。
 * 各カテゴリについて、名前とスラッグ（URL用の識別子）を返します。
 *
 * リクエスト:
 * GET /api/admin/documents/categories
 *
 * レスポンス:
 * 成功: { categories: Array<{ name: string, slug: string }> }
 * 失敗: { error: string }
 */
router.get('/categories', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    const skipAuthCheck = process.env.NODE_ENV !== 'production';

    if (!sessionId && !skipAuthCheck) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    let user = null;
    if (sessionId) {
      user = await sessionService.getSessionUser(sessionId);
    }

    if (!user && !skipAuthCheck) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_SESSION,
      });
    }

    const docsDir = path.join(process.cwd(), 'docs');

    // docs ディレクトリが存在しない場合は作成
    if (!fs.existsSync(docsDir)) {
      fs.mkdirSync(docsDir, { recursive: true });
    }

    // docs 配下のカテゴリを取得
    const items = fs.readdirSync(docsDir, { withFileTypes: true });
    const dirItems = items.filter(item => item.isDirectory() && !item.name.startsWith('.'));

    // カテゴリ情報を拡張してslug情報を含める
    const categories = await Promise.all(
      dirItems.map(async dirItem => {
        const categoryName = dirItem.name;
        const categoryPath = path.join(docsDir, categoryName);
        let slug = categoryName; // デフォルトはカテゴリ名

        // カテゴリ内のmdファイルを探してslugを取得
        try {
          const files = fs.readdirSync(categoryPath, { withFileTypes: true });
          const mdFiles = files.filter(file => file.isFile() && file.name.endsWith('.md'));

          // mdファイルがあれば最初のファイルからslugを取得
          if (mdFiles.length > 0) {
            const firstMdFile = mdFiles[0];
            const filePath = path.join(categoryPath, firstMdFile.name);
            const fileContent = fs.readFileSync(filePath, 'utf8');

            // front-matterを解析
            const { data } = matter(fileContent);

            // slugが存在すればそれを使用
            if (data.slug) {
              slug = data.slug.split('/')[0];
            }
          }
        } catch (err) {
          console.error(`${categoryName}のslug取得中にエラー:`, err);
          // エラーが発生してもカテゴリ名をそのまま使用
        }

        return {
          name: categoryName,
          slug: slug,
        };
      })
    );

    return res.status(HTTP_STATUS.OK).json({
      categories,
    });
  } catch (error) {
    console.error('カテゴリ一覧取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getCategoriesRouter = router;
