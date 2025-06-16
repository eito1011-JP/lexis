import { db } from '@site/src/lib/db';

interface CategoryToUpdate {
  id: number;
  position: number;
  slug: string;
  sidebar_label: string;
  description: string | null;
  parent_id: number | null;
}

export const updateCategoryPositions = async (
  categoriesToUpdate: CategoryToUpdate[],
  userId: number,
  userBranchId: number,
  email: string,
  isMovingUp: boolean
): Promise<void> => {
  if (categoriesToUpdate.length === 0) return;

  const now = new Date().toISOString();
  const insertValues = [];
  const placeholders = [];

  categoriesToUpdate.forEach(category => {
    placeholders.push(`(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`);

    // 移動方向に応じてpositionを調整
    const newPosition = isMovingUp
      ? Number(category.position) + 1 // 上に移動：既存レコードのpositionを+1
      : Number(category.position) - 1; // 下に移動：既存レコードのpositionを-1

    insertValues.push(
      category.slug,
      category.sidebar_label,
      newPosition,
      category.description,
      'draft',
      email,
      userBranchId,
      category.parent_id,
      now,
      now,
      0
    );
  });

  const idsToDelete = categoriesToUpdate.map(row => row.id);

  // 既存レコードを論理削除
  await db.execute({
    sql: `UPDATE document_categories SET is_deleted = 1 WHERE id IN (${idsToDelete.map(() => '?').join(',')})`,
    args: idsToDelete,
  });

  // 新しいレコードを挿入
  await db.execute({
    sql: `INSERT INTO document_categories (
            slug, sidebar_label, position, description,
            status, last_edited_by, user_branch_id, parent_id,
            created_at, updated_at, is_deleted
          ) VALUES ${placeholders.join(', ')}`,
    args: insertValues,
  });
}; 