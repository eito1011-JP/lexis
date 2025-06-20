import { Router, Request, Response } from 'express';
import { getAuthenticatedUser } from '../../../utils/auth';
import { fetchUserBranch } from '../../../utils/git';
import { db } from '@site/src/lib/db';

const router = Router();

// 差分用の型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
  before?: DiffItem | null; // 変更前のデータ
  after?: DiffItem | null; // 変更後のデータ
  change_type: 'created' | 'updated' | 'deleted';
};

router.get('/diff', async (req: Request, res: Response) => {
  try {
    const loginUser = await getAuthenticatedUser(req.cookies.sid);

    if (!loginUser) {
      return res.status(401).json({ error: '認証されていません' });
    }

    // ユーザーのブランチIDを取得
    const userBranchId = await fetchUserBranch(loginUser.userId, 'none');

    if (!userBranchId) {
      return res.status(404).json({
        success: false,
        message: 'ユーザーブランチが見つかりません',
      });
    }

    // カテゴリの差分を取得
    const categoryDiffs = await getCategoryDiffs(userBranchId);

    // ドキュメントの差分を取得
    const documentDiffs = await getDocumentDiffs(userBranchId);

    return res.json({
      success: true,
      categories: categoryDiffs,
      documents: documentDiffs,
      user_branch_id: userBranchId,
    });
  } catch (error) {
    console.error('差分取得中にエラーが発生しました:', error);
    return res.status(500).json({
      success: false,
      message: '差分取得中にエラーが発生しました',
    });
  }
});

// カテゴリの差分を取得する関数
async function getCategoryDiffs(userBranchId: number): Promise<DiffItem[]> {
  // draft状態のカテゴリを取得
  const draftCategories = await db.execute({
    sql: `SELECT * FROM document_categories 
          WHERE user_branch_id = ? AND is_deleted = ? AND status = ?
          ORDER BY parent_id ASC NULLS FIRST, position ASC`,
    args: [userBranchId, 0, 'draft'],
  });

  const diffs: DiffItem[] = [];

  for (const draft of draftCategories.rows || []) {
    // 同じslugのmerged状態のカテゴリを検索
    const mergedResult = await db.execute({
      sql: `SELECT * FROM document_categories 
            WHERE slug = ? AND status = ? AND is_deleted = ? 
            ORDER BY updated_at DESC LIMIT 1`,
      args: [draft.slug, 'merged', 0],
    });

    const merged = mergedResult.rows?.[0] || null;

    diffs.push({
      id: draft.id as number,
      slug: draft.slug as string,
      sidebar_label: draft.sidebar_label as string,
      description: draft.description as string,
      position: draft.position as number,
      parent_id: draft.parent_id as number,
      status: draft.status as string,
      user_branch_id: draft.user_branch_id as number,
      created_at: draft.created_at as string,
      updated_at: draft.updated_at as string,
      before: merged
        ? {
            id: merged.id as number,
            slug: merged.slug as string,
            sidebar_label: merged.sidebar_label as string,
            description: merged.description as string,
            position: merged.position as number,
            parent_id: merged.parent_id as number,
            status: merged.status as string,
            user_branch_id: merged.user_branch_id as number,
            created_at: merged.created_at as string,
            updated_at: merged.updated_at as string,
            change_type: 'updated' as const,
          }
        : null,
      after: {
        id: draft.id as number,
        slug: draft.slug as string,
        sidebar_label: draft.sidebar_label as string,
        description: draft.description as string,
        position: draft.position as number,
        parent_id: draft.parent_id as number,
        status: draft.status as string,
        user_branch_id: draft.user_branch_id as number,
        created_at: draft.created_at as string,
        updated_at: draft.updated_at as string,
        change_type: 'updated' as const,
      },
      change_type: merged ? 'updated' : 'created',
    });
  }

  return diffs;
}

// ドキュメントの差分を取得する関数
async function getDocumentDiffs(userBranchId: number): Promise<DiffItem[]> {
  // draft状態のドキュメントを取得
  const draftDocuments = await db.execute({
    sql: `SELECT * FROM document_versions 
          WHERE user_branch_id = ? AND is_deleted = ? AND status = ?
          ORDER BY category_id ASC NULLS FIRST, file_order ASC`,
    args: [userBranchId, 0, 'draft'],
  });

  const diffs: DiffItem[] = [];

  for (const draft of draftDocuments.rows || []) {
    // 同じslugのmerged状態のドキュメントを検索
    const mergedResult = await db.execute({
      sql: `SELECT * FROM document_versions 
            WHERE slug = ? AND status = ? AND is_deleted = ? 
            ORDER BY updated_at DESC LIMIT 1`,
      args: [draft.slug, 'merged', 0],
    });

    const merged = mergedResult.rows?.[0] || null;

    diffs.push({
      id: draft.id as number,
      slug: draft.slug as string,
      sidebar_label: draft.sidebar_label as string,
      title: draft.title as string,
      content: draft.content as string,
      file_order: draft.file_order as number,
      category_id: draft.category_id as number,
      status: draft.status as string,
      user_branch_id: draft.user_branch_id as number,
      created_at: draft.created_at as string,
      updated_at: draft.updated_at as string,
      before: merged
        ? {
            id: merged.id as number,
            slug: merged.slug as string,
            sidebar_label: merged.sidebar_label as string,
            title: merged.title as string,
            content: merged.content as string,
            file_order: merged.file_order as number,
            category_id: merged.category_id as number,
            status: merged.status as string,
            user_branch_id: merged.user_branch_id as number,
            created_at: merged.created_at as string,
            updated_at: merged.updated_at as string,
            change_type: 'updated' as const,
          }
        : null,
      after: {
        id: draft.id as number,
        slug: draft.slug as string,
        sidebar_label: draft.sidebar_label as string,
        title: draft.title as string,
        content: draft.content as string,
        file_order: draft.file_order as number,
        category_id: draft.category_id as number,
        status: draft.status as string,
        user_branch_id: draft.user_branch_id as number,
        created_at: draft.created_at as string,
        updated_at: draft.updated_at as string,
        change_type: 'updated' as const,
      },
      change_type: merged ? 'updated' : 'created',
    });
  }

  return diffs;
}

export default router;
