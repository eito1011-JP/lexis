import fs from 'fs';
import path from 'path';

// ファイルシステムアイテムの型定義
interface FileSystemItem {
  name: string;
  path: string;
  type: 'file' | 'category';
}

/**
 * docs配下のディレクトリ内容を取得する関数
 *
 * @param categoryPath - docs配下のカテゴリパス（相対パス）
 * @param isOnlyCategories - trueの場合はカテゴリのみ、falseの場合はカテゴリとファイルの両方を取得
 * @returns FileSystemItem[] - ファイルシステムアイテムの配列
 */
export function getDocsCategoryContents(
  categoryPath: string,
  isOnlyCategories: boolean
): FileSystemItem[] {
  try {
    // docs配下のターゲットパスを構築
    const docsDir = path.join(process.cwd(), 'docs');
    const targetPath = path.join(docsDir, categoryPath);

    // ディレクトリが存在しない場合は空配列を返す
    if (!fs.existsSync(targetPath)) {
      return [];
    }

    // ディレクトリ内のアイテムを取得
    const dirItems = fs.readdirSync(targetPath, { withFileTypes: true });

    // isOnlyCategoriesがtrueの場合はカテゴリのみをフィルタリング
    const filteredItems = isOnlyCategories
      ? dirItems.filter(item => item.isDirectory() && !item.name.startsWith('.'))
      : dirItems.filter(item => !item.name.startsWith('.'));

    // FileSystemItem配列に変換
    return filteredItems.map(item => {
      const itemPath = path.join(categoryPath, item.name);
      return {
        name: item.name,
        path: itemPath,
        type: item.isDirectory() ? 'category' : 'file',
      };
    });
  } catch (err) {
    console.error('カテゴリコンテンツ取得エラー:', err);
    return [];
  }
}

/**
 * ディレクトリが存在しない場合に作成する関数
 *
 * @param dirPath - 作成するディレクトリのパス
 */
export function ensureDirectoryExists(dirPath: string): void {
  if (!fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
}
