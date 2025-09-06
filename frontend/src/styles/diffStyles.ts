// GitHub風の差分表示用CSSスタイル（改良版）
export const diffStyles = `
  /* コンテンツラッパー方式のスタイル */
  /* li要素全体に背景色を適用するため、spanの背景色は透明に */
  .diff-deleted-item .diff-deleted-content {
    background-color: transparent !important;
    padding: 0;
    display: inline;
    /* 文字色は通常色を維持してコントラストを保つ */
  }
  
  .diff-added-item .diff-added-content {
    background-color: transparent !important;
    padding: 0;
    display: inline;
    /* 文字色は通常色を維持してコントラストを保つ */
  }
  
  /* 通常の差分コンテンツ（li要素以外での使用） */
  .diff-deleted-content {
    background-color: rgba(248, 81, 73, 0.25) !important;
    border-radius: 3px;
    display: inline;
  }
  
  .diff-added-content {
    background-color: rgba(63, 185, 80, 0.3) !important;
    color: #ffffff !important;
    border-radius: 3px;
    display: inline;
  }
  
  /* Flexboxを使ったカスタムリストスタイル */
  ol {
    list-style: none !important;
    counter-reset: item;
    padding-left: 0 !important;
  }
  
  ol li {
    display: flex !important;
    align-items: flex-start;
    counter-increment: item;
    margin: 4px 0;
    line-height: 1.6;
  }
  
  ol li::before {
    content: counter(item) "." !important;
    min-width: 32px;
    text-align: right;
    padding-right: 8px;
    margin-right: 8px;
    flex-shrink: 0;
  }
  
  /* 削除差分要素のスタイル */
  .diff-deleted-item {
    margin: 2px 0;
    background-color: rgba(248, 81, 73, 0.25) !important;
    border-radius: 4px;
    padding: 4px 0;
  }
  
  .diff-deleted-item::before {
    /* 番号部分の背景色は削除し、li要素全体の背景色のみ使用 */
    margin-right: 8px !important;
  }
  
  /* 追加差分要素のスタイル */
  .diff-added-item {
    margin: 2px 0;
    background-color: rgba(63, 185, 80, 0.3) !important;
    border-radius: 4px;
    padding: 4px 0;
    color: #ffffff !important;
  }
  
  .diff-added-item::before {
    /* 番号部分の背景色は削除し、li要素全体の背景色のみ使用 */
    color: #ffffff !important;
    margin-right: 8px !important;
  }
  
  /* 通常のリスト要素 */
  ol li:not(.diff-deleted-item):not(.diff-added-item)::before {
    color: inherit;
    background-color: transparent;
  }
  
  /* 従来のスタイルも保持（フォールバック用） */
  .diff-deleted {
    background-color: rgba(248, 81, 73, 0.25) !important;
    border-radius: 3px;
    padding: 2px 4px;
    margin: 2px 0;
    display: inline;
  }
  
  .diff-added {
    background-color: rgba(63, 185, 80, 0.3) !important;
    color: #ffffff !important;
    border-radius: 3px;
    padding: 2px 4px;
    margin: 2px 0;
    display: inline;
  }
  
  /* リスト要素の基本スタイル */
  ol, ul {
    margin: 16px 0;
    padding-left: 24px;
  }
  
  li {
    margin: 2px 0;
    line-height: 1.6;
  }
  
  .diff-added-content li::marker {
    color: #ffffff;
  }
  
  /* 追加・削除コンテナ内の要素スタイル */
  .diff-added-container * {
    color: #ffffff !important;
  }
  
  .diff-deleted-container * {
    color: #ff6b6b !important;
  }
  
  .diff-added-container h1,
  .diff-added-container h2,
  .diff-added-container h3,
  .diff-added-container h4,
  .diff-added-container h5,
  .diff-added-container h6 {
    color: #ffffff !important;
    border-bottom-color: rgba(255, 255, 255, 0.3) !important;
  }
  
  .diff-deleted-container h1,
  .diff-deleted-container h2,
  .diff-deleted-container h3,
  .diff-deleted-container h4,
  .diff-deleted-container h5,
  .diff-deleted-container h6 {
    border-bottom-color: rgba(255, 107, 107, 0.3) !important;
  }
  
  /* ブロック要素専用の差分スタイル */
  .diff-block-added {
    background-color: rgba(63, 185, 80, 0.3) !important;
    width: fit-content;
    border-radius: 4px;
    margin: 4px 0 !important;
    display: block !important;
    color: #ffffff !important;
  }

  .diff-block-deleted {
    background-color: rgba(248, 81, 73, 0.25) !important;
    width: fit-content;
    border-radius: 4px;
    margin: 4px 0 !important;
    display: block !important;
  }

  /* ブロック要素内のテキストスタイル */
  .diff-block-added h1,
  .diff-block-added h2,
  .diff-block-added h3,
  .diff-block-added h4,
  .diff-block-added h5,
  .diff-block-added h6 {
    color: #ffffff !important;
    margin: 0 !important;
    padding: 0 !important;
    border-bottom: none !important;
  }

  .diff-block-deleted h1,
  .diff-block-deleted h2,
  .diff-block-deleted h3,
  .diff-block-deleted h4,
  .diff-block-deleted h5,
  .diff-block-deleted h6 {
    margin: 0 !important;
    padding: 0 !important;
    border-bottom: none !important;
  }

  /* ブロック要素内の段落スタイル */
  .diff-block-added p {
    color: #ffffff !important;
    margin: 0 !important;
  }

  .diff-block-deleted p {
    margin: 0 !important;
  }

  .diff-added-content {
    background-color: rgba(34, 197, 94, 0.2);
    color: #16a34a;
  }

  .diff-deleted-content {
    background-color: rgba(239, 68, 68, 0.2);
    color: #dc2626;
  }

  .diff-block-added {
    background-color: rgba(34, 197, 94, 0.1);
    padding: 0.25rem;
    margin: 0.125rem 0;
  }

  .diff-block-deleted {
    background-color: rgba(239, 68, 68, 0.1);
    padding: 0.25rem;
    margin: 0.125rem 0;
  }


`;
