import { db } from '../lib/db';

/**
 * カテゴリパスからカテゴリIDを取得する
 * @param categoryPath カテゴリパスの配列（例: ['getting-started', 'installation']）
 * @returns カテゴリID。見つからない場合はデフォルトカテゴリのIDを返す
 */
export const getCategoryIdByPath = async (categoryPath: string[]): Promise<number | null> => {
  if (categoryPath.length === 0) {
    return await getDefaultCategoryId();
  }

  let parentId: number = 1;
  let currentCategoryId: number | null = null;

  for (const path of categoryPath) {
    // パスを/で分割して階層を取得
    const pathParts = path.split('/');

    for (const slug of pathParts) {
      const categoryId = await getCategoryIdBySlug(slug, parentId);
      if (categoryId) {
        parentId = categoryId;
        currentCategoryId = categoryId;
      } else {
        return await getDefaultCategoryId();
      }
    }
  }

  return currentCategoryId;
};

/**
 * デフォルトカテゴリのIDを取得する
 * @returns デフォルトカテゴリのID
 */
const getDefaultCategoryId = async (): Promise<number | null> => {
  const result = await db.execute({
    sql: 'SELECT id FROM document_categories WHERE slug = ?',
    args: ['uncategorized'],
  });
  return result.rows[0]?.id ? Number(result.rows[0].id) : null;
};

/**
 * スラッグと親カテゴリIDからカテゴリIDを取得する
 * @param slug カテゴリのスラッグ
 * @param parentId 親カテゴリのID
 * @returns カテゴリID
 */
const getCategoryIdBySlug = async (
  slug: string,
  parentId: number | null
): Promise<number | null> => {
  const result = await db.execute({
    sql: 'SELECT id FROM document_categories WHERE slug = ? AND parent_id IS ?',
    args: [slug, parentId],
  });
  return result.rows[0]?.id ? Number(result.rows[0].id) : null;
};
