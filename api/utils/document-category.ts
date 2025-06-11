import { db } from '../../src/lib/db';

/**
 * デフォルトカテゴリのIDを取得する
 */
export const getDefaultCategoryId = async (): Promise<number | null> => {
  const result = await db.execute({
    sql: 'SELECT id FROM document_categories WHERE slug = ?',
    args: ['uncategorized'],
  });
  return result.rows[0]?.id ? Number(result.rows[0].id) : null;
};

/**
 * スラッグと親カテゴリIDからカテゴリIDを取得する
 */
export const getCategoryIdBySlug = async (
  slug: string,
  parentId: number | null
): Promise<number | null> => {
  const result = await db.execute({
    sql: 'SELECT id FROM document_categories WHERE slug = ? AND parent_id IS ?',
    args: [slug, parentId],
  });
  return result.rows[0]?.id ? Number(result.rows[0].id) : null;
};

/**
 * カテゴリパスからカテゴリIDを取得する
 * @param categoryPath カテゴリパスの配列（例: ['category1', 'subcategory1']）
 * @returns カテゴリID、見つからない場合はデフォルトカテゴリID
 */
export const getCategoryIdFromPath = async (categoryPath: string[]): Promise<number | null> => {
  if (categoryPath.length === 0) {
    return await getDefaultCategoryId();
  }

  let parentId: number = 1;
  let currentCategoryId: number | null = null;
  console.log('parentId', parentId);
  console.log('currentCategoryId', currentCategoryId);
  console.log('categoryPath', categoryPath);

  for (const slug of categoryPath) {
    const categoryId = await getCategoryIdBySlug(slug, parentId);
    if (categoryId) {
      parentId = categoryId;
      currentCategoryId = categoryId;
    } else {
      return await getDefaultCategoryId();
    }
  }

  return currentCategoryId;
};
