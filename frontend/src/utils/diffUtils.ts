import { makeDiff, cleanupSemantic, makePatches, stringifyPatches } from '@sanity/diff-match-patch';

// 差分計算とHTML生成の関数
export const generateDiffHtml = (originalText: string, currentText: string): string => {
  // makeDiffを使って差分のタプル配列を作成
  const diffs = makeDiff(originalText || '', currentText || '');

  // より読みやすい差分にするため、意味的なクリーンアップを実行
  const cleanedDiffs = cleanupSemantic(diffs);

  // カスタムHTMLレンダリング
  let html = '';
  for (const [operation, text] of cleanedDiffs) {
    const escapedText = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\n/g, '<br/>');

    switch (operation) {
      case -1: // 削除
        html += `<span class="diff-deleted-content">${escapedText}</span>`;
        break;
      case 1: // 追加
        html += `<span class="diff-added-content">${escapedText}</span>`;
        break;
      case 0: // 変更なし
        html += escapedText;
        break;
    }
  }

  return html;
};

// パッチ情報を生成する関数（デバッグや詳細表示用）
export const generatePatchInfo = (originalText: string, currentText: string): string => {
  try {
    // makePatches でパッチ配列を作成
    const patches = makePatches(originalText || '', currentText || '');

    // stringifyPatches で unidiff形式の文字列に変換
    const patchString = stringifyPatches(patches);

    return patchString;
  } catch (error) {
    console.error('パッチ生成エラー:', error);
    return '';
  }
};

// マークダウンテキストに差分マーカーを挿入する関数
export const insertDiffMarkersInText = (originalText: string, currentText: string): string => {
  const diffs = makeDiff(originalText || '', currentText || '');
  const cleanedDiffs = cleanupSemantic(diffs);

  let markedText = '';
  cleanedDiffs.forEach(([operation, text]) => {
    if (operation === -1) {
      // 削除された部分にマーカーを追加
      markedText += `<DIFF_DELETE>${text}</DIFF_DELETE>`;
    } else if (operation === 1) {
      // 追加された部分にマーカーを追加
      markedText += `<DIFF_ADD>${text}</DIFF_ADD>`;
    } else {
      // 変更なし
      markedText += text;
    }
  });

  // デバッグ用：差分マーカーの確認
  if (process.env.NODE_ENV === 'development') {
    console.log('Original:', originalText);
    console.log('Current:', currentText);
    console.log('Marked Text:', markedText);
  }

  return markedText;
};

// HTMLに変換後、差分マーカーを適切なspanタグに置換する関数
export const replaceDiffMarkersInHtml = (html: string): string => {
  // デバッグ用：処理前のHTMLを確認
  if (process.env.NODE_ENV === 'development') {
    console.log('HTML before processing:', html);
  }

  let processedHtml = html;

  // 複数要素にまたがる差分マーカーを検出して処理
  // 実際のパターン: <li>要素2<DIFF_DELETE></li>\n<li>要素3</DIFF_DELETE></li>
  // 注意: 要素2は差分対象ではなく、改行+要素3のみが削除対象
  processedHtml = processedHtml.replace(
    /(<li[^>]*>)([^<]*)<DIFF_DELETE><\/li>\s*(<li[^>]*>)([^<]*)<\/DIFF_DELETE>/g,
    (match: string, li1Tag: string, content1: string, li2Tag: string, content2: string) => {
      // 2番目のli要素のみにクラスを追加（削除対象は要素3のみ）
      const li2WithClass = li2Tag.includes('class=')
        ? li2Tag.replace(/class="([^"]*)"/, 'class="$1 diff-deleted-item"')
        : li2Tag.replace('>', ' class="diff-deleted-item">');

      // 1番目の要素は通常表示、2番目の要素のみ差分表示
      return `${li1Tag}${content1}</li>\n${li2WithClass}<span class="diff-deleted-content">${content2}</span></li>`;
    }
  );

  processedHtml = processedHtml.replace(
    /(<li[^>]*>)([^<]*)<DIFF_ADD><\/li>\s*(<li[^>]*>)([^<]*)<\/DIFF_ADD>/g,
    (match: string, li1Tag: string, content1: string, li2Tag: string, content2: string) => {
      // 2番目のli要素のみにクラスを追加（追加対象は要素3のみ）
      const li2WithClass = li2Tag.includes('class=')
        ? li2Tag.replace(/class="([^"]*)"/, 'class="$1 diff-added-item"')
        : li2Tag.replace('>', ' class="diff-added-item">');

      // 1番目の要素は通常表示、2番目の要素のみ差分表示
      return `${li1Tag}${content1}</li>\n${li2WithClass}<span class="diff-added-content">${content2}</span></li>`;
    }
  );

  // より複雑なケース：複数のli要素にまたがる場合
  processedHtml = processedHtml.replace(
    /<DIFF_DELETE>([\s\S]*?)<\/DIFF_DELETE>/g,
    (match: string, content: string) => {
      // 内部にli要素が含まれている場合の処理
      if (content.includes('<li>') || content.includes('</li>')) {
        // li要素ごとに分割して処理
        return content.replace(
          /(<li)([^>]*>)(.*?)(<\/li>)/g,
          (
            liMatch: string,
            openTagStart: string,
            attributes: string,
            liContent: string,
            closeTag: string
          ) => {
            // li要素全体に差分クラスを適用（マーカーも含めて色を変更）
            const existingClass = attributes.match(/class="([^"]*)"/) || ['', ''];
            const newClass = existingClass[1]
              ? `${existingClass[1]} diff-deleted-item`
              : 'diff-deleted-item';
            const newAttributes = attributes.replace(/class="[^"]*"/, '').trim();

            return `${openTagStart} class="${newClass}"${newAttributes ? ' ' + newAttributes : ''}><span class="diff-deleted-content">${liContent}</span>${closeTag}`;
          }
        );
      } else {
        return `<span class="diff-deleted-content">${content}</span>`;
      }
    }
  );

  processedHtml = processedHtml.replace(
    /<DIFF_ADD>([\s\S]*?)<\/DIFF_ADD>/g,
    (match: string, content: string) => {
      // 内部にli要素が含まれている場合の処理
      if (content.includes('<li>') || content.includes('</li>')) {
        // li要素ごとに分割して処理
        return content.replace(
          /(<li)([^>]*>)(.*?)(<\/li>)/g,
          (
            liMatch: string,
            openTagStart: string,
            attributes: string,
            liContent: string,
            closeTag: string
          ) => {
            // li要素全体に差分クラスを適用（マーカーも含めて色を変更）
            const existingClass = attributes.match(/class="([^"]*)"/) || ['', ''];
            const newClass = existingClass[1]
              ? `${existingClass[1]} diff-added-item`
              : 'diff-added-item';
            const newAttributes = attributes.replace(/class="[^"]*"/, '').trim();

            return `${openTagStart} class="${newClass}"${newAttributes ? ' ' + newAttributes : ''}><span class="diff-added-content">${liContent}</span>${closeTag}`;
          }
        );
      } else {
        return `<span class="diff-added-content">${content}</span>`;
      }
    }
  );

  // 単一要素内の通常の差分マーカーを置換
  processedHtml = processedHtml
    .replace(/<DIFF_DELETE>(.*?)<\/DIFF_DELETE>/gs, '<span class="diff-deleted-content">$1</span>')
    .replace(/<DIFF_ADD>(.*?)<\/DIFF_ADD>/gs, '<span class="diff-added-content">$1</span>');

  // デバッグ用：処理後のHTMLを確認
  if (process.env.NODE_ENV === 'development') {
    console.log('HTML after processing:', processedHtml);
  }

  return processedHtml;
};

// データをslugでマップ化する関数
export const mapBySlug = (items: any[]) => {
  return items.reduce(
    (acc, item) => {
      acc[item.slug] = item;
      return acc;
    },
    {} as Record<string, any>
  );
};
