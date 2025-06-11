import { db } from '../../src/lib/db';

/**
 * デフォルトカテゴリのIDを取得する
 */
export const getDefaultCategoryId = async (): Promise<number | null> => {
  const result = await db.execute({
    sql: 'SELECT id FROM document_categories WHERE slug = ? AND parent_id IS ?',
    args: ['uncategorized', null],
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
export const getCategoryIdFromPath = async (categoryPath: string[] | string): Promise<number | null> => {
  // 文字列の場合は配列に変換
  const pathArray = Array.isArray(categoryPath) ? categoryPath : [categoryPath];

  if (pathArray.length === 0) {
    return await getDefaultCategoryId();
  }

  let parentId: number = 1;
  let currentCategoryId: number | null = null;

  for (const slug of pathArray) {
    if (parentId === 1) {
      parentId = await getDefaultCategoryId();
    }
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
