import { Router, Request, Response } from 'express';
import { getAuthenticatedUser } from '../../../utils/auth';
import { db } from '@site/src/lib/db';

const router = Router();

// 差分用の型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  content?: string;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  is_deleted: number;
  created_at: string;
  updated_at: string;
};

type DiffResponse = {
  documents: Array<{
    original: DiffItem | null;
    current: DiffItem;
  }>;
  categories: Array<{
    original: DiffItem | null;
    current: DiffItem;
  }>;
};

router.get('/', async (req: Request, res: Response) => {
  try {
    const loginUser = await getAuthenticatedUser(req.cookies.sid);

    if (!loginUser) {
      return res.status(401).json({ error: '認証されていません' });
    }

    // リクエストからuser_branch_idを取得
    const userBranchId = req.query.user_branch_id;

    if (!userBranchId || typeof userBranchId !== 'string') {
      return res.status(400).json({
        success: false,
        message: 'user_branch_idが必要です',
      });
    }

    const branchId = parseInt(userBranchId, 10);

    if (isNaN(branchId)) {
      return res.status(400).json({
        success: false,
        message: '有効なuser_branch_idを指定してください',
      });
    }

    // 差分情報を取得
    const diffData = await getDiffData(branchId);

    return res.json({
      success: true,
      ...diffData,
    });
  } catch (error) {
    console.error('差分取得中にエラーが発生しました:', error);
    return res.status(500).json({
      success: false,
      message: '差分取得中にエラーが発生しました',
    });
  }
});

// 差分データを取得する関数
async function getDiffData(userBranchId: number): Promise<DiffResponse> {
  // 1. edit_start_versionsからdocumentとcategoryの差分情報を取得
  const documentEditVersions = await db.execute({
    sql: `SELECT * FROM edit_start_versions 
          WHERE target_type = 'document' AND user_branch_id = ?
          ORDER BY created_at ASC`,
    args: [userBranchId],
  });

  const categoryEditVersions = await db.execute({
    sql: `SELECT * FROM edit_start_versions 
          WHERE target_type = 'category' AND user_branch_id = ?
          ORDER BY created_at ASC`,
    args: [userBranchId],
  });

  // 2. original_version_idとcurrent_version_idを変数にまとめる
  const documentOriginalIds: number[] = [];
  const documentCurrentIds: number[] = [];
  const categoryOriginalIds: number[] = [];
  const categoryCurrentIds: number[] = [];

  // documentのIDを収集
  for (const editVersion of documentEditVersions.rows || []) {
    if (editVersion.original_version_id) {
      documentOriginalIds.push(editVersion.original_version_id as number);
    }
    if (editVersion.current_version_id) {
      documentCurrentIds.push(editVersion.current_version_id as number);
    }
  }

  // categoryのIDを収集
  for (const editVersion of categoryEditVersions.rows || []) {
    if (editVersion.original_version_id) {
      categoryOriginalIds.push(editVersion.original_version_id as number);
    }
    if (editVersion.current_version_id) {
      categoryCurrentIds.push(editVersion.current_version_id as number);
    }
  }

  // 3. document_versionsとdocument_categoriesのレコードを取得
  const originalDocuments =
    documentOriginalIds.length > 0
      ? await db.execute({
          sql: `SELECT * FROM document_versions WHERE id IN (${documentOriginalIds.map(() => '?').join(',')})`,
          args: documentOriginalIds,
        })
      : { rows: [] };

  const currentDocuments =
    documentCurrentIds.length > 0
      ? await db.execute({
          sql: `SELECT * FROM document_versions WHERE id IN (${documentCurrentIds.map(() => '?').join(',')})`,
          args: documentCurrentIds,
        })
      : { rows: [] };

  const originalCategories =
    categoryOriginalIds.length > 0
      ? await db.execute({
          sql: `SELECT * FROM document_categories WHERE id IN (${categoryOriginalIds.map(() => '?').join(',')})`,
          args: categoryOriginalIds,
        })
      : { rows: [] };

  const currentCategories =
    categoryCurrentIds.length > 0
      ? await db.execute({
          sql: `SELECT * FROM document_categories WHERE id IN (${categoryCurrentIds.map(() => '?').join(',')})`,
          args: categoryCurrentIds,
        })
      : { rows: [] };

  // 4. 差分データを組み立て
  const documents = buildDocumentDiffs(
    documentEditVersions.rows || [],
    originalDocuments.rows || [],
    currentDocuments.rows || []
  );
  const categories = buildCategoryDiffs(
    categoryEditVersions.rows || [],
    originalCategories.rows || [],
    currentCategories.rows || []
  );

  return {
    documents,
    categories,
  };
}

// ドキュメントの差分を組み立てる関数
function buildDocumentDiffs(
  editVersions: any[],
  originalDocuments: any[],
  currentDocuments: any[]
): Array<{ original: DiffItem | null; current: DiffItem }> {
  const diffs: Array<{ original: DiffItem | null; current: DiffItem }> = [];

  for (const editVersion of editVersions) {
    const original = originalDocuments.find(doc => doc.id === editVersion.original_version_id);
    const current = currentDocuments.find(doc => doc.id === editVersion.current_version_id);

    if (current) {
      diffs.push({
        original: original ? mapDocumentToDiffItem(original) : null,
        current: mapDocumentToDiffItem(current),
      });
    }
  }

  return diffs;
}

// カテゴリの差分を組み立てる関数
function buildCategoryDiffs(
  editVersions: any[],
  originalCategories: any[],
  currentCategories: any[]
): Array<{ original: DiffItem | null; current: DiffItem }> {
  const diffs: Array<{ original: DiffItem | null; current: DiffItem }> = [];

  for (const editVersion of editVersions) {
    const original = originalCategories.find(cat => cat.id === editVersion.original_version_id);
    const current = currentCategories.find(cat => cat.id === editVersion.current_version_id);

    if (current) {
      diffs.push({
        original: original ? mapCategoryToDiffItem(original) : null,
        current: mapCategoryToDiffItem(current),
      });
    }
  }

  return diffs;
}

// ドキュメントをDiffItemにマッピングする関数
function mapDocumentToDiffItem(doc: any): DiffItem {
  return {
    id: doc.id as number,
    slug: doc.slug as string,
    sidebar_label: doc.sidebar_label as string,
    content: doc.content as string,
    file_order: doc.file_order as number,
    category_id: doc.category_id as number,
    status: doc.status as string,
    user_branch_id: doc.user_branch_id as number,
    is_deleted: doc.is_deleted as number,
    created_at: doc.created_at as string,
    updated_at: doc.updated_at as string,
  };
}

// カテゴリをDiffItemにマッピングする関数
function mapCategoryToDiffItem(cat: any): DiffItem {
  return {
    id: cat.id as number,
    slug: cat.slug as string,
    sidebar_label: cat.sidebar_label as string,
    description: cat.description as string,
    position: cat.position as number,
    parent_id: cat.parent_id as number,
    status: cat.status as string,
    user_branch_id: cat.user_branch_id as number,
    is_deleted: cat.is_deleted as number,
    created_at: cat.created_at as string,
    updated_at: cat.updated_at as string,
  };
}

export default router;
