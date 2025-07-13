import React, { useCallback, useMemo, useState } from 'react';
import './styles.css';
import {
  createEditor,
  Descendant,
  Transforms,
  Editor,
  Element as SlateElement,
  Text,
  Range,
  BaseEditor,
} from 'slate';
import {
  Slate,
  Editable,
  withReact,
  ReactEditor,
  RenderElementProps,
  RenderLeafProps,
} from 'slate-react';
import { withHistory, HistoryEditor } from 'slate-history';
import { Bold as BoldIcon } from '../../icon/editor/Bold';
import { Italic as ItalicIcon } from '../../icon/editor/Italic';
import { UnderLine as UnderLineIcon } from '../../icon/editor/UnderLine';
import { BulletList as BulletListIcon } from '../../icon/editor/BulletList';
import { StrikeThrow as StrikeThrowIcon } from '../../icon/editor/StrikeThrow';
import { Quote as QuoteIcon } from '../../icon/editor/Quote';
import { OrderedList as OrderedListIcon } from '../../icon/editor/OrderedList';
import { CodeBlock as CodeBlockIcon } from '../../icon/editor/CodeBlock';
import { Image as ImageIcon } from '../../icon/common/Image';
import { TextFormat } from '../../icon/editor/TextFormat';
import { Paragraph as ParagraphIcon } from '../../icon/editor/Paragraph';
import Toggle from '../../icon/editor/Toggle';

interface SlateEditorProps {
  initialContent: string;
  onChange: (html: string) => void;
  placeholder?: string;
}

// カスタム型定義
type CustomElement = {
  type:
    | 'paragraph'
    | 'heading-one'
    | 'heading-two'
    | 'heading-three'
    | 'block-quote'
    | 'bulleted-list'
    | 'numbered-list'
    | 'list-item'
    | 'code'
    | 'image'
    | 'link';
  children: CustomText[];
  url?: string;
};

type CustomText = {
  text: string;
  bold?: boolean;
  italic?: boolean;
  underline?: boolean;
  strike?: boolean;
  code?: boolean;
  fontSize?: string;
};

declare module 'slate' {
  interface CustomTypes {
    Editor: BaseEditor & ReactEditor & HistoryEditor;
    Element: CustomElement;
    Text: CustomText;
  }
}

const LIST_TYPES = ['numbered-list', 'bulleted-list'] as const;

const SlateEditor: React.FC<SlateEditorProps> = ({
  initialContent,
  onChange,
  placeholder = 'ここにドキュメントを作成してください',
}) => {
  const [showParagraphOptions, setShowParagraphOptions] = useState(false);
  const [showFontSizeOptions, setShowFontSizeOptions] = useState(false);
  const editor = useMemo(() => withImages(withHistory(withReact(createEditor()))), []);

  // Markdown→Slate変換は省略し、初期値はプレーンテキストとして挿入
  const safeInitialContent = typeof initialContent === 'string' ? initialContent : '';
  const initialValue: Descendant[] = useMemo(
    () =>
      safeInitialContent.trim()
        ? [
            {
              type: 'paragraph',
              children: [{ text: safeInitialContent }],
            } as CustomElement,
          ]
        : [
            {
              type: 'paragraph',
              children: [{ text: '' }],
            } as CustomElement,
          ],
    [safeInitialContent]
  );

  // HTML変換（簡易）
  const serialize = (nodes: Descendant[]): string => {
    return nodes
      .map(n => {
        if (Editor.isEditor(n) || !('type' in n)) return Text.isText(n) ? n.text : '';
        const element = n as CustomElement;
        switch (element.type) {
          case 'heading-one':
            return `<h1>${serialize(element.children)}</h1>`;
          case 'heading-two':
            return `<h2>${serialize(element.children)}</h2>`;
          case 'heading-three':
            return `<h3>${serialize(element.children)}</h3>`;
          case 'block-quote':
            return `<blockquote>${serialize(element.children)}</blockquote>`;
          case 'bulleted-list':
            return `<ul>${serialize(element.children)}</ul>`;
          case 'numbered-list':
            return `<ol>${serialize(element.children)}</ol>`;
          case 'list-item':
            return `<li>${serialize(element.children)}</li>`;
          case 'code':
            return `<pre><code>${serialize(element.children)}</code></pre>`;
          case 'image':
            return `<img src="${element.url}" alt="" />`;
          case 'link':
            return `<a href="${element.url}" target="_blank" rel="noopener noreferrer">${serialize(element.children)}</a>`;
          case 'paragraph':
          default:
            return `<p>${serialize(element.children)}</p>`;
        }
      })
      .join('');
  };

  // onChange
  const handleChange = (newValue: Descendant[]) => {
    onChange(serialize(newValue));
  };

  // Render Element
  const renderElement = useCallback((props: RenderElementProps) => <Element {...props} />, []);
  const renderLeaf = useCallback((props: RenderLeafProps) => <Leaf {...props} />, []);

  // Toolbarの各種コマンド
  const exec = (format: string, value?: any) => {
    console.log('Executing format:', format);
    switch (format) {
      case 'bold':
      case 'italic':
      case 'underline':
      case 'strike':
      case 'code':
        toggleMark(editor, format);
        break;
      case 'heading-one':
      case 'heading-two':
      case 'heading-three':
      case 'paragraph':
      case 'block-quote':
      case 'bulleted-list':
      case 'numbered-list':
        toggleBlock(editor, format);
        break;
      case 'image':
        insertImage(editor);
        break;
      case 'font-size':
        setFontSize(editor, value);
        break;
      case 'link':
        insertLink(editor);
        break;
      case 'undo':
        (editor as HistoryEditor).undo();
        break;
      case 'redo':
        (editor as HistoryEditor).redo();
        break;
      default:
        break;
    }
  };

  // UI
  return (
    <div className="w-full relative slate-editor">
      <div className="flex mb-2 pb-5 pt-1 px-1 border-b gap-1 rounded-t">
        {/* 段落・見出し */}
        <div className="relative h-8">
          <button
            className={`h-8 bg-transparent rounded hover:border-[#B1B1B1] border border-transparent ${showParagraphOptions ? 'border-[#B1B1B1]' : ''}`}
            title="段落スタイル"
            onClick={() => {
              setShowParagraphOptions(!showParagraphOptions);
              setShowFontSizeOptions(false);
            }}
          >
            <div className="flex items-center gap-1">
              <ParagraphIcon width={15} height={15} />
              <Toggle width={9} height={9} />
            </div>
          </button>
          <div
            className={`absolute ${showParagraphOptions ? 'block' : 'hidden'} bg-white border rounded shadow-lg z-10 w-32`}
          >
            <button
              onClick={() => {
                exec('paragraph');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100"
            >
              段落
            </button>
            <button
              onClick={() => {
                exec('heading-one');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100"
            >
              見出し 1
            </button>
            <button
              onClick={() => {
                exec('heading-two');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100"
            >
              見出し 2
            </button>
            <button
              onClick={() => {
                exec('heading-three');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100"
            >
              見出し 3
            </button>
          </div>
        </div>
        <div className="flex items-center h-8 mx-0.5">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* フォントサイズ */}
        <div className="relative h-8">
          <button
            className={`h-8 bg-transparent rounded hover:border-[#B1B1B1] border border-transparent ${showFontSizeOptions ? 'border-[#B1B1B1]' : ''}`}
            title="フォントサイズ"
            onClick={() => {
              setShowFontSizeOptions(!showFontSizeOptions);
              setShowParagraphOptions(false);
            }}
          >
            <div className="flex items-center gap-1">
              <TextFormat width={22} height={22} />
              <Toggle width={9} height={9} />
            </div>
          </button>
          <div
            className={`absolute ${showFontSizeOptions ? 'block' : 'hidden'} bg-white border rounded shadow-lg z-10 w-32`}
          >
            <button
              onClick={() => {
                exec('font-size', '12px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-xs"
            >
              小 (12px)
            </button>
            <button
              onClick={() => {
                exec('font-size', '16px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100"
            >
              中 (16px)
            </button>
            <button
              onClick={() => {
                exec('font-size', '20px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-lg"
            >
              大 (20px)
            </button>
            <button
              onClick={() => {
                exec('font-size', '24px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-xl"
            >
              特大 (24px)
            </button>
          </div>
        </div>
        <div className="flex items-center h-8 mx-0.5">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* マーク */}
        <button
          onClick={() => exec('bold')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="bold"
        >
          <BoldIcon width={16} height={16} />
        </button>
        <button
          onClick={() => exec('italic')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="italic"
        >
          <ItalicIcon width={16} height={16} />
        </button>
        <button
          onClick={() => exec('underline')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="underline"
        >
          <UnderLineIcon width={16} height={16} />
        </button>
        <button
          onClick={() => exec('strike')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="strike"
        >
          <StrikeThrowIcon width={16} height={16} />
        </button>
        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* リスト・引用・コード */}
        <button
          onClick={() => exec('bulleted-list')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="bullet-list"
        >
          <BulletListIcon width={16} height={16} />
        </button>
        <button
          onClick={() => exec('numbered-list')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="ordered-list"
        >
          <OrderedListIcon width={19} height={19} />
        </button>
        <button
          onClick={() => exec('block-quote')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="blockquote"
        >
          <QuoteIcon width={16} height={16} />
        </button>
        <button
          onClick={() => exec('code')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="code block"
        >
          <CodeBlockIcon width={16} height={16} />
        </button>
        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* 画像 */}
        <button
          onClick={() => exec('image')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="image"
        >
          <ImageIcon width={16} height={16} />
        </button>
        {/* リンク */}
        <button
          onClick={() => exec('link')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="link"
        >
          🔗
        </button>
        {/* Undo/Redo */}
        <button
          onClick={() => exec('undo')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="undo"
        >
          ⎌
        </button>
        <button
          onClick={() => exec('redo')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="redo"
        >
          ⎌⎌
        </button>
      </div>
      <div className="rounded-b">
        <div className="w-full pt-4">
          <Slate
            editor={editor as ReactEditor}
            initialValue={initialValue}
            onValueChange={handleChange}
          >
            <Editable
              renderElement={renderElement}
              renderLeaf={renderLeaf}
              placeholder={placeholder}
              spellCheck
              autoFocus
              className="outline-none w-full min-h-[200px] p-2"
              onKeyDown={event => {
                if ((event.metaKey || event.ctrlKey) && event.key === 'z') {
                  event.preventDefault();
                  (editor as HistoryEditor).undo();
                }
                if ((event.metaKey || event.ctrlKey) && event.shiftKey && event.key === 'z') {
                  event.preventDefault();
                  (editor as HistoryEditor).redo();
                }
              }}
            />
          </Slate>
        </div>
      </div>
    </div>
  );
};

// --- Slate用ユーティリティ ---

const toggleMark = (editor: Editor, format: string) => {
  const isActive = isMarkActive(editor, format);
  if (isActive) {
    Editor.removeMark(editor, format);
  } else {
    Editor.addMark(editor, format, true);
  }
};

const isMarkActive = (editor: Editor, format: string) => {
  const marks = Editor.marks(editor);
  return marks ? (marks as any)[format] === true : false;
};

const toggleBlock = (editor: Editor, format: string) => {
  const isActive = isBlockActive(editor, format);
  const isList = LIST_TYPES.includes(format as any);
  const isHeading = ['heading-one', 'heading-two', 'heading-three'].includes(format);
  
  // リスト要素をアンラップ
  Transforms.unwrapNodes(editor, {
    match: n =>
      !Editor.isEditor(n) &&
      SlateElement.isElement(n) &&
      LIST_TYPES.includes((n as CustomElement).type as any),
    split: true,
  });
  
  // 見出し要素をアンラップ（新しい見出しに変換する場合を除く）
  if (!isHeading) {
    Transforms.unwrapNodes(editor, {
      match: n =>
        !Editor.isEditor(n) &&
        SlateElement.isElement(n) &&
        ['heading-one', 'heading-two', 'heading-three'].includes((n as CustomElement).type),
      split: true,
    });
  }
  
  if (!isActive && isList) {
    // リストアイテムに変換
    Transforms.setNodes<CustomElement>(editor, { type: 'list-item' });
    // リストでラップ
    const block: CustomElement = { type: format as CustomElement['type'], children: [] };
    Transforms.wrapNodes(editor, block);
  } else {
    // 通常のブロック変換
    let newType = isActive ? 'paragraph' : format;
    Transforms.setNodes<CustomElement>(editor, { type: newType as CustomElement['type'] });
  }
};

const isBlockActive = (editor: Editor, format: string) => {
  const { selection } = editor;
  if (!selection) return false;
  
  const [match] = Editor.nodes(editor, {
    match: n =>
      !Editor.isEditor(n) && SlateElement.isElement(n) && (n as CustomElement).type === format,
    at: Editor.unhangRange(editor, selection),
  });
  return !!match;
};

const insertImage = (editor: Editor) => {
  const url = window.prompt('画像URLを入力してください');
  if (!url) return;
  const image: CustomElement = { type: 'image', url, children: [{ text: '' }] };
  Transforms.insertNodes(editor, image);
};

const withImages = (editor: Editor) => {
  const { isVoid } = editor;
  editor.isVoid = element => {
    return (element as CustomElement).type === 'image' ? true : isVoid(element);
  };
  return editor;
};

const setFontSize = (editor: Editor, size: string) => {
  Editor.addMark(editor, 'fontSize', size);
};

const insertLink = (editor: Editor) => {
  const url = window.prompt('リンクURLを入力してください');
  if (!url) return;
  const { selection } = editor;
  const isCollapsed = selection && Range.isCollapsed(selection);
  if (isCollapsed) {
    const link: CustomElement = {
      type: 'link',
      url,
      children: [{ text: url }],
    };
    Transforms.insertNodes(editor, link);
  } else {
    const link: CustomElement = { type: 'link', url, children: [] };
    Transforms.wrapNodes(editor, link, { split: true });
    Transforms.collapse(editor, { edge: 'end' });
  }
};

// --- Slate用Element/Leaf ---

const Element: React.FC<RenderElementProps> = ({ attributes, children, element }) => {
  const customElement = element as CustomElement;
  switch (customElement.type) {
    case 'heading-one':
      return <h1 {...attributes}>{children}</h1>;
    case 'heading-two':
      return <h2 {...attributes}>{children}</h2>;
    case 'heading-three':
      return <h3 {...attributes}>{children}</h3>;
    case 'block-quote':
      return <blockquote {...attributes}>{children}</blockquote>;
    case 'bulleted-list':
      return <ul {...attributes}>{children}</ul>;
    case 'numbered-list':
      return <ol {...attributes}>{children}</ol>;
    case 'list-item':
      return <li {...attributes}>{children}</li>;
    case 'code':
      return (
        <pre {...attributes}>
          <code>{children}</code>
        </pre>
      );
    case 'image':
      return (
        <img
          {...attributes}
          src={customElement.url}
          alt=""
          style={{ maxWidth: '100%', height: 'auto' }}
        />
      );
    case 'link':
      return (
        <a
          {...attributes}
          href={customElement.url}
          target="_blank"
          rel="noopener noreferrer"
          style={{ color: '#00a000', textDecoration: 'none' }}
        >
          {children}
        </a>
      );
    default:
      return <p {...attributes}>{children}</p>;
  }
};

const Leaf: React.FC<RenderLeafProps> = ({ attributes, children, leaf }) => {
  const customLeaf = leaf as CustomText;
  if (customLeaf.bold) {
    children = <strong>{children}</strong>;
  }
  if (customLeaf.italic) {
    children = <em>{children}</em>;
  }
  if (customLeaf.underline) {
    children = <u>{children}</u>;
  }
  if (customLeaf.strike) {
    children = <s>{children}</s>;
  }
  if (customLeaf.code) {
    children = <code>{children}</code>;
  }
  if (customLeaf.fontSize) {
    children = <span style={{ fontSize: customLeaf.fontSize }}>{children}</span>;
  }
  return <span {...attributes}>{children}</span>;
};

export default SlateEditor;
