import fs from 'fs';
import path from 'path';

interface FileSystemItem {
  name: string;
  path: string;
  type: 'file' | 'folder';
}

/**
 * docs配下の指定されたパスのフォルダとファイルを取得する
 * @param folderPath - docs配下のフォルダパス（相対パス）
 * @param isOnlyFolders - trueの場合はフォルダのみ、falseの場合はフォルダとファイルの両方を取得
 * @returns FileSystemItem[] - ファイルとフォルダの情報配列
 */
export function getDocsFolderContents(folderPath: string, isOnlyFolders: boolean): FileSystemItem[] {
  try {
    // docsディレクトリからの相対パスを構築
    const docsDir = path.join(process.cwd(), 'docs');
    const targetPath = path.join(docsDir, folderPath);

    // 指定されたパスが存在しない場合は空配列を返す
    if (!fs.existsSync(targetPath)) {
      return [];
    }

    // ディレクトリの内容を取得
    const items = fs.readdirSync(targetPath, { withFileTypes: true });
    
    // フォルダとファイルを処理
    return items
      .filter(item => {
        // isOnlyFoldersがtrueの場合はフォルダのみをフィルタリング
        if (isOnlyFolders) {
          return item.isDirectory();
        }
        // falseの場合はすべてのアイテムを含める
        return true;
      })
      .map(item => {
        const itemPath = path.join(folderPath, item.name);
        return {
          name: item.name,
          path: itemPath,
          type: item.isDirectory() ? 'folder' : 'file'
        };
      });
  } catch (error) {
    console.error(`ファイルシステム操作エラー: ${error}`);
    return [];
  }
} 
