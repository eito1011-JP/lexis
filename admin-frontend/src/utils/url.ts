/**
 * URLエンコード関連のユーティリティ関数
 */

/**
 * カテゴリパスとスラッグを結合してエンコードする
 * @param categoryPath カテゴリパス
 * @param slug スラッグ
 * @returns エンコードされたパス
 */
export const encodeCategoryPath = (categoryPath: string | null, slug: string): string => {
  const fullPath = categoryPath ? `${categoryPath}/${slug}` : slug;
  return encodeURIComponent(fullPath);
};

/**
 * パスをエンコードする
 * @param path エンコードするパス
 * @returns エンコードされたパス
 */
export const encodePath = (path: string): string => {
  return encodeURIComponent(path);
}; 