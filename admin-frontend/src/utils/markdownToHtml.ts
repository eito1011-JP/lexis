// MarkdownをHTMLに変換する関数
export const markdownToHtml = (markdown: string): string => {
  if (!markdown) return '';

  let html = markdown;

  // 改行を統一
  html = html.replace(/\r\n/g, '\n');
  html = html.replace(/\r/g, '\n');

  // コードブロック（最初に処理して保護）
  const codeBlocks: string[] = [];
  html = html.replace(/```([\s\S]*?)```/g, (match, code) => {
    const index = codeBlocks.length;
    codeBlocks.push(`<pre><code>${code.trim()}</code></pre>`);
    return `__CODE_BLOCK_${index}__`;
  });

  // インラインコード（保護）
  const inlineCodes: string[] = [];
  html = html.replace(/`([^`]+)`/g, (match, code) => {
    const index = inlineCodes.length;
    inlineCodes.push(`<code>${code}</code>`);
    return `__INLINE_CODE_${index}__`;
  });

  // 見出し（Setext形式: === と ---）
  html = html.replace(/^(.+)\n=+$/gm, '<h1>$1</h1>');
  html = html.replace(/^(.+)\n-+$/gm, '<h2>$1</h2>');

  // 見出し（ATX形式: # ## ###）
  html = html.replace(/^######\s+(.*)$/gm, '<h6>$1</h6>');
  html = html.replace(/^#####\s+(.*)$/gm, '<h5>$1</h5>');
  html = html.replace(/^####\s+(.*)$/gm, '<h4>$1</h4>');
  html = html.replace(/^###\s+(.*)$/gm, '<h3>$1</h3>');
  html = html.replace(/^##\s+(.*)$/gm, '<h2>$1</h2>');
  html = html.replace(/^#\s+(.*)$/gm, '<h1>$1</h1>');

  // 太字
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');

  // 斜体
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
  html = html.replace(/_(.*?)_/g, '<em>$1</em>');

  // 削除線
  html = html.replace(/~~(.*?)~~/g, '<del>$1</del>');

  // 水平線
  html = html.replace(/^[\s]*[-\*_]{3,}[\s]*$/gm, '<hr>');

  // 引用
  html = html.replace(/^>\s*(.*)$/gm, '<blockquote>$1</blockquote>');

  // リンク
  html = html.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
  );

  // 画像
  html = html.replace(
    /!\[([^\]]*)\]\(([^)]+)\)/g,
    '<img src="$2" alt="$1" style="max-width: 100%; height: auto;" />'
  );

  // リストの処理
  const lines = html.split('\n');
  const processedLines: string[] = [];
  let inUnorderedList = false;
  let inOrderedList = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const isUnorderedListItem = /^[\s]*[-\*\+]\s+(.*)$/.test(line);
    const isOrderedListItem = /^[\s]*\d+\.\s+(.*)$/.test(line);

    if (isUnorderedListItem) {
      if (!inUnorderedList) {
        if (inOrderedList) {
          processedLines.push('</ol>');
          inOrderedList = false;
        }
        processedLines.push('<ul>');
        inUnorderedList = true;
      }
      const content = line.replace(/^[\s]*[-\*\+]\s+(.*)$/, '$1');
      processedLines.push(`<li>${content}</li>`);
    } else if (isOrderedListItem) {
      if (!inOrderedList) {
        if (inUnorderedList) {
          processedLines.push('</ul>');
          inUnorderedList = false;
        }
        processedLines.push('<ol>');
        inOrderedList = true;
      }
      const content = line.replace(/^[\s]*\d+\.\s+(.*)$/, '$1');
      processedLines.push(`<li>${content}</li>`);
    } else {
      if (inUnorderedList) {
        processedLines.push('</ul>');
        inUnorderedList = false;
      }
      if (inOrderedList) {
        processedLines.push('</ol>');
        inOrderedList = false;
      }
      processedLines.push(line);
    }
  }

  // 残りのリストを閉じる
  if (inUnorderedList) {
    processedLines.push('</ul>');
  }
  if (inOrderedList) {
    processedLines.push('</ol>');
  }

  html = processedLines.join('\n');

  // 段落の処理
  const paragraphs = html.split('\n\n');
  const processedParagraphs = paragraphs.map(paragraph => {
    const trimmed = paragraph.trim();
    if (!trimmed) return '';

    // ブロック要素は段落でラップしない
    if (
      trimmed.startsWith('<h') ||
      trimmed.startsWith('<ul') ||
      trimmed.startsWith('<ol') ||
      trimmed.startsWith('<blockquote') ||
      trimmed.startsWith('<pre') ||
      trimmed.startsWith('<hr') ||
      trimmed.includes('</h') ||
      trimmed.includes('</ul>') ||
      trimmed.includes('</ol>') ||
      trimmed.includes('</blockquote>') ||
      trimmed.includes('</pre>')
    ) {
      return trimmed;
    }

    // 単一行の改行を<br>に変換
    const withBreaks = trimmed.replace(/\n/g, '<br>');
    return `<p>${withBreaks}</p>`;
  });

  html = processedParagraphs.filter(p => p).join('\n\n');

  // 保護したコードブロックを復元
  codeBlocks.forEach((codeBlock, index) => {
    html = html.replace(`__CODE_BLOCK_${index}__`, codeBlock);
  });

  // 保護したインラインコードを復元
  inlineCodes.forEach((inlineCode, index) => {
    html = html.replace(`__INLINE_CODE_${index}__`, inlineCode);
  });

  return html.trim();
};
