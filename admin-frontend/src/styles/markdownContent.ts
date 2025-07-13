// マークダウンコンテンツ用の共通CSS-in-JSスタイル
export const markdownStyles = `
  .markdown-content {
    line-height: 1.6;
    font-size: 14px;
  }
  
  /* 見出しスタイル（SlateEditorと同様） */
  .markdown-content h1 {
    font-size: 2em;
    font-weight: bold;
    margin: 0.67em 0;
    line-height: 1.2;
    color: inherit;
  }
  
  .markdown-content h2 {
    font-size: 1.5em;
    font-weight: bold;
    margin: 0.83em 0;
    line-height: 1.3;
    color: inherit;
  }
  
  .markdown-content h3 {
    font-size: 1.17em;
    font-weight: bold;
    margin: 1em 0;
    line-height: 1.4;
    color: inherit;
  }
  
  .markdown-content h4 {
    font-size: 1em;
    font-weight: bold;
    margin: 1.33em 0;
    line-height: 1.4;
    color: inherit;
  }
  
  .markdown-content h5 {
    font-size: 0.83em;
    font-weight: bold;
    margin: 1.67em 0;
    line-height: 1.4;
    color: inherit;
  }
  
  .markdown-content h6 {
    font-size: 0.67em;
    font-weight: bold;
    margin: 2.33em 0;
    line-height: 1.4;
    color: inherit;
  }
  
  /* 段落スタイル */
  .markdown-content p {
    margin: 1em 0;
    line-height: 1.6;
  }
  
  .markdown-content p:first-child {
    margin-top: 0;
  }
  
  .markdown-content p:last-child {
    margin-bottom: 0;
  }
  
  /* リストスタイル（SlateEditorと同様） */
  .markdown-content ul,
  .markdown-content ol {
    margin: 1em 0;
    padding-left: 2em;
  }
  
  .markdown-content li {
    margin: 0.25em 0;
    line-height: 1.6;
  }
  
  .markdown-content ul li {
    list-style-type: disc;
  }
  
  .markdown-content ol li {
    list-style-type: decimal;
  }
  
  /* 引用スタイル（SlateEditorと同様、ダークテーマ対応） */
  .markdown-content blockquote {
    border-left: 4px solid rgba(255, 255, 255, 0.3);
    margin: 1em 0;
    padding-left: 1em;
    opacity: 0.8;
    font-style: italic;
  }
  
  /* コードブロックスタイル（SlateEditorと同様、ダークテーマ対応） */
  .markdown-content pre {
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    padding: 1em;
    overflow-x: auto;
    margin: 1em 0;
    font-family: 'Courier New', 'Monaco', 'Consolas', monospace;
    font-size: 13px;
    line-height: 1.4;
  }
  
  .markdown-content pre code {
    background-color: transparent;
    padding: 0;
    border: none;
    font-size: inherit;
  }
  
  /* インラインコードスタイル（SlateEditorと同様、ダークテーマ対応） */
  .markdown-content code {
    background-color: rgba(255, 255, 255, 0.1);
    padding: 0.2em 0.4em;
    border-radius: 3px;
    font-family: 'Courier New', 'Monaco', 'Consolas', monospace;
    font-size: 13px;
    font-weight: 500;
  }
  
  /* リンクスタイル */
  .markdown-content a {
    color: #60a5fa;
    text-decoration: underline;
    text-underline-offset: 2px;
  }
  
  .markdown-content a:hover {
    color: #93c5fd;
  }
  
  /* 強調スタイル */
  .markdown-content strong {
    font-weight: bold;
    color: inherit;
  }
  
  .markdown-content em {
    font-style: italic;
  }
  
  /* 水平線スタイル */
  .markdown-content hr {
    border: 0;
    height: 1px;
    background-color: rgba(255, 255, 255, 0.2);
    margin: 2em 0;
  }
  
  /* テーブルスタイル */
  .markdown-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .markdown-content th,
  .markdown-content td {
    padding: 0.75em;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .markdown-content th {
    background-color: rgba(255, 255, 255, 0.05);
    font-weight: bold;
    color: inherit;
  }
  
  /* 画像スタイル */
  .markdown-content img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin: 1em 0;
  }
  
  /* 削除線スタイル */
  .markdown-content del,
  .markdown-content s {
    text-decoration: line-through;
    opacity: 0.7;
  }
  
  /* 最初の要素のマージン調整 */
  .markdown-content > *:first-child {
    margin-top: 0;
  }
  
  /* 最後の要素のマージン調整 */
  .markdown-content > *:last-child {
    margin-bottom: 0;
  }
`; 