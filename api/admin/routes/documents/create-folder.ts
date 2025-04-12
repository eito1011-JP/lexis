import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';

const router = Router();

router.post('/create-folder', async (req: Request, res: Response) => {
  try {
    const { folderName } = req.body;

    // TODO: フォルダ作成の実装
    // 1. データベースにフォルダ情報を保存
    // 2. ファイルシステムにフォルダを作成

    return res.status(HTTP_STATUS.CREATED).json({
      message: 'フォルダが作成されました',
      folderName
    });
  } catch (error) {
    console.error('フォルダ作成エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR
    });
  }
});

export const createFolderRouter = router; 