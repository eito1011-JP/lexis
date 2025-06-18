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
 * 指定されたslugのカテゴリとその子カテゴリ、ドキュメントを再帰的に取得する
 * @param slug カテゴリのスラッグ
 * @param userBranchId ユーザーブランチID
 * @returns カテゴリとドキュメントの配列
 */
export async function getCategoryTreeFromSlug(slug: string, userBranchId: number) {
  const categories = [];
  const documents = [];
  
  // 指定されたslugのカテゴリを取得
  const rootCategoryResult = await db.execute({
    sql: 'SELECT * FROM document_categories WHERE slug = ? AND user_branch_id = ? AND is_deleted = 0 LIMIT 1',
    args: [slug, userBranchId],
  });
    
  if (rootCategoryResult.rows.length === 0) {
    throw new Error('Category not found');
  }
  
  const rootCategory = rootCategoryResult.rows[0];
  
  // 再帰的にカテゴリとドキュメントを取得
  async function collectCategoryTree(categoryId: number) {
    // 現在のカテゴリを取得
    const categoryResult = await db.execute({
      sql: 'SELECT * FROM document_categories WHERE id = ? AND user_branch_id = ? AND is_deleted = 0 LIMIT 1',
      args: [categoryId, userBranchId],
    });
      
    if (categoryResult.rows.length > 0) {
      const category = categoryResult.rows[0];
      categories.push(category);
      
      // このカテゴリに属するドキュメントを取得（document_versionsテーブルのみから）
      const categoryDocumentsResult = await db.execute({
        sql: `SELECT * FROM document_versions 
              WHERE category_id = ? AND user_branch_id = ? 
              AND is_deleted = 0`,
        args: [categoryId, userBranchId],
      });
        
      documents.push(...categoryDocumentsResult.rows);
      
      // 子カテゴリを再帰的に処理
      const childCategoriesResult = await db.execute({
        sql: 'SELECT * FROM document_categories WHERE parent_id = ? AND user_branch_id = ? AND is_deleted = 0',
        args: [categoryId, userBranchId],
      });
        
      for (const child of childCategoriesResult.rows) {
        await collectCategoryTree(Number(child.id));
      }
    }
  }
  
  await collectCategoryTree(Number(rootCategory.id));
  
  return { categories, documents };
}

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
