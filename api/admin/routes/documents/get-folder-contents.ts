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

router.get('/:slug', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;
    const { slug } = req.params;

    // 認証チェック
    const loginUser = await sessionService.getSessionUser(sessionId);
    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }
    
    // docs配下のフォルダを取得して、与えられたslugに一致するフォルダを探す
    const docsDir = path.join(process.cwd(), 'docs');
    const dirItems = fs.readdirSync(docsDir, { withFileTypes: true });
    const folders = dirItems.filter(item => item.isDirectory());
    
    // 各フォルダ内のmdファイルをチェックしてslugを確認
    let targetDir = null;
    let folderPath = '';
    
    for (const folder of folders) {
      const folderName = folder.name;
      const currentFolderPath = path.join(docsDir, folderName);
      
      // スラッグが単にフォルダ名と一致する場合
      if (folderName === slug) {
        targetDir = currentFolderPath;
        folderPath = folderName;
        break;
      }
      
      // フォルダ内のmdファイルをチェック
      try {
        const files = fs.readdirSync(currentFolderPath, { withFileTypes: true });
        const mdFiles = files.filter(file => file.isFile() && file.name.endsWith('.md'));
        
        for (const mdFile of mdFiles) {
          const filePath = path.join(currentFolderPath, mdFile.name);
          const fileContent = fs.readFileSync(filePath, 'utf8');
          
          // front-matterを解析
          const { data } = matter(fileContent);
          
          if (data.slug === slug) {
            targetDir = currentFolderPath;
            folderPath = folderName;
            break;
          }
        }
        
        if (targetDir) break;
      } catch (err) {
        console.error(`${folderName}内のファイル読み込みエラー:`, err);
      }
    }
    
    // 指定されたスラグに一致するフォルダが見つからない
    if (!targetDir) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: 'Folder not found',
      });
    }
    
    // ディレクトリの内容を取得
    const dirContents = fs.readdirSync(targetDir, { withFileTypes: true });
    
    // ファイルとフォルダに分類して処理
    const contentItems = await Promise.all(dirContents.map(async (item) => {
      const itemPath = path.join(folderPath, item.name);
      
      if (item.isDirectory()) {
        // フォルダの場合
        return {
          type: 'folder',
          name: item.name,
          path: itemPath
        };
      } else if (item.isFile() && item.name.endsWith('.md')) {
        // マークダウンファイルの場合
        const filePath = path.join(targetDir, item.name);
        const fileContent = fs.readFileSync(filePath, 'utf8');
        
        // front-matterを解析
        const { data, content } = matter(fileContent);
        
        return {
          type: 'document',
          name: data.sidebar_label || item.name.replace('.md', ''),
          path: itemPath.replace('.md', ''),
          status: data.is_public ? '公開' : '未公開',
          lastEditor: data.last_editor || '---',
          content: content.substring(0, 100) + (content.length > 100 ? '...' : '')
        };
      }
      
      // その他のファイルは無視
      return null;
    }));
    
    console.log(dirContents);
    // nullでない項目のみフィルタリング
    const validItems = contentItems.filter(item => item !== null);

    return res.status(HTTP_STATUS.OK).json({
      items: validItems,
    });
  } catch (error) {
    console.error('フォルダコンテンツ取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getFolderContentsRouter = router; 