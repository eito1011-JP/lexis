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

router.get('/folders', async (req: Request, res: Response) => {
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

    // docs 配下のフォルダを取得
    const items = fs.readdirSync(docsDir, { withFileTypes: true });
    const dirItems = items.filter(item => item.isDirectory());
    
    // フォルダ情報を拡張してslug情報を含める
    const folders = await Promise.all(dirItems.map(async (dirItem) => {
      const folderName = dirItem.name;
      const folderPath = path.join(docsDir, folderName);
      let slug = folderName; // デフォルトはフォルダ名
      
      // フォルダ内のmdファイルを探してslugを取得
      try {
        const files = fs.readdirSync(folderPath, { withFileTypes: true });
        const mdFiles = files.filter(file => file.isFile() && file.name.endsWith('.md'));
        
        // mdファイルがあれば最初のファイルからslugを取得
        if (mdFiles.length > 0) {
          const firstMdFile = mdFiles[0];
          const filePath = path.join(folderPath, firstMdFile.name);
          const fileContent = fs.readFileSync(filePath, 'utf8');
          
          // front-matterを解析
          const { data } = matter(fileContent);
          
          // slugが存在すればそれを使用
          if (data.slug) {
            slug = data.slug;
          }
        }
      } catch (err) {
        console.error(`${folderName}のslug取得中にエラー:`, err);
        // エラーが発生してもフォルダ名をそのまま使用
      }
      
      return {
        name: folderName,
        slug: slug
      };
    }));

    return res.status(HTTP_STATUS.OK).json({
      folders,
    });
  } catch (error) {
    console.error('フォルダ一覧取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getFoldersRouter = router;
