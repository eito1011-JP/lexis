import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { sessionService } from '../../../../src/services/sessionService';
import fs from 'fs';
import path from 'path';
import matter from 'gray-matter';
import { db } from '@site/src/lib/db';

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

// 特定のslugのドキュメントを取得するAPI
router.get('/:slug', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;
    const { slug } = req.params;
    const category = req.query.category as string;
    console.log('slug', slug);
    // 認証チェック
    const loginUser = await sessionService.getSessionUser(sessionId);
    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    const apiDir = path.dirname(path.dirname(path.dirname(path.dirname(__filename))));
    const rootDir = path.dirname(apiDir);
    const docsDir = path.join(rootDir, 'docs');

    // カテゴリパスを構築
    const categoryPath = category ? category.split('/').filter(Boolean) : [];
    const targetDir = path.join(docsDir, ...categoryPath);
    console.log('targetDir', targetDir);

    // MDファイルの検索パターン
    const possibleFiles = [`${slug}.md`, `${slug}/index.md`];

    let documentData = null;

    // MDファイルが存在するか確認
    for (const fileName of possibleFiles) {
      const filePath = path.join(targetDir, fileName);
      if (fs.existsSync(filePath)) {
        const fileContent = fs.readFileSync(filePath, 'utf8');
        // front-matterを解析
        const { data, content } = matter(fileContent);

        documentData = {
          slug: slug,
          label: data.sidebar_label || data.label || slug,
          content: content,
          position: data.position || 999,
          isPublic: data.isPublic !== false, // デフォルトは公開する
          reviewerEmail: data.last_reviewed_by || null,
          lastEditedBy: data.last_edited_by || null,
          description: data.description || '',
          source: 'md_file',
        };
        break;
      }
    }

    // MDファイルが見つからない場合、データベースからドラフトデータを取得
    if (!documentData) {
      const draftDocumentResult = await db.execute({
        sql: 'SELECT * FROM document_versions WHERE slug = ? AND category_path = ? AND status = ? ORDER BY created_at DESC LIMIT 1',
        args: [slug, category || '', 'draft'],
      });

      if (draftDocumentResult.rows.length > 0) {
        const draftDocument = draftDocumentResult.rows[0];
        documentData = {
          slug: slug,
          label: draftDocument.title || draftDocument.sidebar_label,
          content: draftDocument.content,
          position: draftDocument.display_order || 999,
          isPublic: draftDocument.is_public === 1,
          reviewerEmail: draftDocument.reviewer_email,
          lastEditedBy: draftDocument.last_edited_by,
          description: draftDocument.description || '',
          source: 'database',
        };
      }
    }

    console.log('documentData', documentData);

    if (!documentData) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: 'Document not found',
      });
    }

    return res.status(HTTP_STATUS.OK).json(documentData);
  } catch (error) {
    console.error('ドキュメント取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getDocumentBySlugRouter = router;
