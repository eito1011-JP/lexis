import { db } from '@site/src/lib/db';

interface DocumentToUpdate {
  id: number;
  file_order: number;
  file_path: string;
  content: string;
  slug: string;
  sidebar_label: string;
  is_public: boolean;
  category_id: number;
}

export const updateDocumentFileOrders = async (
  documentsToUpdate: DocumentToUpdate[],
  userId: number,
  userBranchId: number,
  email: string,
  isMovingUp: boolean
): Promise<void> => {
  if (documentsToUpdate.length === 0) return;

  const now = new Date().toISOString();
  const insertValues = [];
  const placeholders = [];

  documentsToUpdate.forEach(doc => {
    placeholders.push(`(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`);

    // 移動方向に応じてfile_orderを調整
    const newFileOrder = isMovingUp
      ? Number(doc.file_order) + 1 // 上に移動：既存レコードのfile_orderを+1
      : Number(doc.file_order) - 1; // 下に移動：既存レコードのfile_orderを-1

    insertValues.push(
      userId,
      userBranchId,
      doc.file_path,
      'draft',
      doc.content,
      doc.slug,
      doc.sidebar_label,
      newFileOrder,
      email,
      now,
      now,
      0,
      doc.is_public,
      doc.category_id
    );
  });

  const idsToDelete = documentsToUpdate.map(row => row.id);

  // 既存レコードを論理削除
  await db.execute({
    sql: `UPDATE document_versions SET is_deleted = 1 WHERE id IN (${idsToDelete.map(() => '?').join(',')})`,
    args: idsToDelete,
  });

  // 新しいレコードを挿入
  await db.execute({
    sql: `INSERT INTO document_versions (
            user_id, user_branch_id, file_path, status, content, slug,
            sidebar_label, file_order, last_edited_by, created_at, updated_at,
            is_deleted, is_public, category_id
          ) VALUES ${placeholders.join(', ')}`,
    args: insertValues,
  });
};
