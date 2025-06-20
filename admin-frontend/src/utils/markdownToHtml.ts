// MarkdownをHTMLに変換する簡易的な関数
export const markdownToHtml = (markdown: string): string => {
  if (!markdown) return '';

  let html = markdown;

  // 見出し
  html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
  html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
  html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');

  // 太字
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');

  // 斜体
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
  html = html.replace(/_(.*?)_/g, '<em>$1</em>');

  // コードブロック
  html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');

  // インラインコード
  html = html.replace(/`(.*?)`/g, '<code>$1</code>');

  // リスト
  html = html.replace(/^\* (.*$)/gim, '<li>$1</li>');
  html = html.replace(/^- (.*$)/gim, '<li>$1</li>');
  html = html.replace(/^(\d+)\. (.*$)/gim, '<li>$2</li>');

  // リストのラッパー（簡易的な実装）
  html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');

  // 引用
  html = html.replace(/^> (.*$)/gim, '<blockquote>$1</blockquote>');

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

  // 改行を段落に変換
  html = html.replace(/\n\n/g, '</p><p>');
  html = html.replace(/\n/g, '<br />');

  // 段落でラップ
  if (
    !html.startsWith('<h') &&
    !html.startsWith('<ul') &&
    !html.startsWith('<ol') &&
    !html.startsWith('<blockquote') &&
    !html.startsWith('<pre')
  ) {
    html = `<p>${html}</p>`;
  }

  return html;
};
