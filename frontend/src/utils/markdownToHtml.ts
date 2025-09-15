import React from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import rehypeSanitize from 'rehype-sanitize';
import { defaultSchema } from 'rehype-sanitize';

// セキュリティ設定：許可するHTMLタグと属性を定義
const sanitizeSchema = {
  ...defaultSchema,
  attributes: {
    ...defaultSchema.attributes,
    // 画像にstyle属性を許可（max-width: 100%; height: auto;）
    img: [...(defaultSchema.attributes?.img || []), 'style'],
    // リンクにtarget、rel属性を許可
    a: [...(defaultSchema.attributes?.a || []), 'target', 'rel'],
  },
};

// react-markdownのコンポーネントカスタマイズ
const markdownComponents = {
  // 画像にレスポンシブスタイルを適用
  img: ({ src, alt, ...props }: any) => {
    return React.createElement('img', {
      src,
      alt,
      style: { maxWidth: '100%', height: 'auto' },
      ...props
    });
  },
  // リンクに外部リンク属性を適用
  a: ({ href, children, ...props }: any) => {
    return React.createElement('a', {
      href,
      target: '_blank',
      rel: 'noopener noreferrer',
      ...props
    }, children);
  },
  // 段落内の改行を<br>タグに変換
  p: ({ children, ...props }: any) => {
    // 子要素を文字列として処理し、改行を<br>に変換
    const processChildren = (children: any): any => {
      if (typeof children === 'string') {
        return children.split('\n').map((line: string, index: number, array: string[]) => 
          index < array.length - 1 ? 
            React.createElement(React.Fragment, { key: index }, line, React.createElement('br')) : 
            line
        );
      }
      if (Array.isArray(children)) {
        return children.map((child, index) => 
          typeof child === 'string' && child.includes('\n') ?
            React.createElement(React.Fragment, { key: index }, ...processChildren(child)) :
            child
        );
      }
      return children;
    };

    return React.createElement('p', props, processChildren(children));
  },
};

// MarkdownをReactコンポーネントとして変換する関数
export const MarkdownRenderer: React.FC<{ children: string }> = ({ children }) => {
  if (!children) return null;

  return React.createElement(ReactMarkdown, {
    remarkPlugins: [remarkGfm],
    rehypePlugins: [[rehypeSanitize, sanitizeSchema]],
    components: markdownComponents,
    children: children
  });
};

// 後方互換性のためのHTML文字列変換関数
export const markdownToHtml = (markdown: string): string => {
  if (!markdown) return '';

  // react-markdownを使用したReactコンポーネントをHTMLに変換するのは複雑なため、
  // この関数は段階的移行のために残していますが、
  // 新しいコードではMarkdownRendererコンポーネントの使用を推奨します
  console.warn('markdownToHtml は非推奨です。MarkdownRenderer コンポーネントを使用してください。');
  
  // 簡易的なフォールバック実装（セキュリティ上の理由で最小限の機能のみ）
  let html = markdown;
  
  // 基本的なエスケープ処理
  html = html.replace(/&/g, '&amp;');
  html = html.replace(/</g, '&lt;');
  html = html.replace(/>/g, '&gt;');
  html = html.replace(/"/g, '&quot;');
  html = html.replace(/'/g, '&#x27;');
  
  // 段落として包む
  return `<p>${html.replace(/\n/g, '<br>')}</p>`;
};
