import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import Placeholder from '@tiptap/extension-placeholder';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import Underline from '@tiptap/extension-underline';
import Strike from '@tiptap/extension-strike';
import BulletList from '@tiptap/extension-bullet-list';
import ListItem from '@tiptap/extension-list-item';
import Blockquote from '@tiptap/extension-blockquote';
import Image from '@tiptap/extension-image';
import Heading from '@tiptap/extension-heading';
import { EditorContent, useEditor } from '@tiptap/react';
import React, { useEffect, useRef, useState } from 'react';
import { Extension } from '@tiptap/core';
import TextStyle from '@tiptap/extension-text-style';
import Toggle from '../../icon/editor/Toggle';
import { TextFormat } from '../../icon/editor/TextFormat';
import { Paragraph as ParagraphIcon } from '../../icon/editor/Paragraph';
import { Bold as BoldIcon } from '../../icon/editor/Bold';
import { Italic as ItalicIcon } from '../../icon/editor/Italic';
import { UnderLine as UnderLineIcon } from '../../icon/editor/UnderLine';
import { Image as ImageIcon } from '../../icon/common/Image';
import { BulletList as BulletListIcon } from '../../icon/editor/BulletList';
import { StrikeThrow as StrikeThrowIcon } from '../../icon/editor/StrikeThrow';
import { Quote as QuoteIcon } from '../../icon/editor/Quote';
import OrderedList from '@tiptap/extension-ordered-list';
import { OrderedList as OrderedListIcon } from '../../icon/editor/OrderedList';
import CodeBlock from '@tiptap/extension-code-block';
import { CodeBlock as CodeBlockIcon } from '../../icon/editor/CodeBlock';

// カスタムエクステンション: フォントサイズをサポート
const FontSize = Extension.create({
  name: 'fontSize',

  addAttributes() {
    return {
      fontSize: {
        default: null,
        parseHTML: element => element.style.fontSize,
        renderHTML: attributes => {
          if (!attributes.fontSize) {
            return {};
          }
          return {
            style: `font-size: ${attributes.fontSize}`,
          };
        },
      },
    };
  },

  addGlobalAttributes() {
    return [
      {
        types: ['textStyle'],
        attributes: {
          fontSize: {
            default: null,
            parseHTML: element => element.style.fontSize,
            renderHTML: attributes => {
              if (!attributes.fontSize) {
                return {};
              }
              return {
                style: `font-size: ${attributes.fontSize}`,
              };
            },
          },
        },
      },
    ];
  },

  addCommands() {
    return {
      setFontSize:
        fontSize =>
        ({ chain }) => {
          return chain().setMark('textStyle', { fontSize: fontSize }).run();
        },
    };
  },
});

interface TiptapEditorProps {
  initialContent: string;
  onChange: (html: string) => void;
  placeholder?: string;
}

const TiptapEditor: React.FC<TiptapEditorProps> = ({
  initialContent,
  onChange,
  placeholder = 'ここにドキュメントを作成してください',
}) => {
  const [lineCount, setLineCount] = useState<number>(1);
  const [showParagraphOptions, setShowParagraphOptions] = useState<boolean>(false);
  const [showFontSizeOptions, setShowFontSizeOptions] = useState<boolean>(false);
  const editorRef = useRef<HTMLDivElement>(null);
  const paragraphMenuRef = useRef<HTMLDivElement>(null);
  const fontSizeMenuRef = useRef<HTMLDivElement>(null);

  const editor = useEditor({
    extensions: [
      Document,
      Paragraph,
      Text.configure({
        HTMLAttributes: {
          class: 'editor-text',
        },
      }),
      Bold,
      Italic,
      Underline,
      Strike,
      BulletList,
      ListItem,
      OrderedList,
      Blockquote,
      CodeBlock.configure({
        languageClassPrefix: 'language-',
        HTMLAttributes: {
          class: 'code-block',
        },
      }),
      Image,
      Heading.configure({
        levels: [1, 2, 3, 4, 5, 6],
      }),
      TextStyle,
      FontSize,
      Placeholder.configure({
        placeholder,
        emptyEditorClass: 'is-editor-empty',
      }),
    ],
    content: initialContent,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
      updateLineCount();
    },
  });

  const updateLineCount = () => {
    if (editorRef.current && editor) {
      // DOM要素を直接使用して段落数をカウント
      const paragraphs = editor.view.dom.querySelectorAll('p');
      setLineCount(paragraphs.length || 1);
    }
  };

  useEffect(() => {
    if (editor) {
      // エディタの初期化後に行数を更新
      setTimeout(updateLineCount, 100);
    }
  }, [editor]);

  const toggleBold = () => {
    editor?.chain().focus().toggleBold().run();
  };

  const toggleItalic = () => {
    editor?.chain().focus().toggleItalic().run();
  };

  const toggleUnderline = () => {
    editor?.chain().focus().toggleUnderline().run();
  };

  const toggleStrike = () => {
    editor?.chain().focus().toggleStrike().run();
  };

  const toggleBulletList = () => {
    editor?.chain().focus().toggleBulletList().run();
  };

  const toggleOrderedList = () => {
    editor?.chain().focus().toggleOrderedList().run();
  };

  const toggleBlockquote = () => {
    editor?.chain().focus().toggleBlockquote().run();
  };

  const toggleCodeBlock = () => {
    editor?.chain().focus().toggleCodeBlock().run();
  };

  const addImage = () => {
    const url = window.prompt('画像URLを入力してください');
    if (url) {
      editor?.chain().focus().setImage({ src: url }).run();
    }
  };

  const setParagraph = () => {
    editor?.chain().focus().setParagraph().run();
  };

  const setHeading = (level: 1 | 2 | 3 | 4 | 5 | 6) => {
    editor?.chain().focus().toggleHeading({ level }).run();
  };

  const setFontSize = (size: string) => {
    // 現在の選択範囲にフォントサイズを適用
    editor?.chain().focus().setFontSize(size).run();
  };

  // クリックイベントのハンドラ追加
  useEffect(() => {
    // メニュー外のクリックを監視して閉じる
    const handleClickOutside = (event: MouseEvent) => {
      if (paragraphMenuRef.current && !paragraphMenuRef.current.contains(event.target as Node)) {
        setShowParagraphOptions(false);
      }
      if (fontSizeMenuRef.current && !fontSizeMenuRef.current.contains(event.target as Node)) {
        setShowFontSizeOptions(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const toggleParagraphMenu = () => {
    setShowParagraphOptions(!showParagraphOptions);
    setShowFontSizeOptions(false); // 他のメニューを閉じる
  };

  const toggleFontSizeMenu = () => {
    setShowFontSizeOptions(!showFontSizeOptions);
    setShowParagraphOptions(false); // 他のメニューを閉じる
  };

  return (
    <div className="w-full relative">
      <div className="flex mb-2 pb-5 pt-1 px-1 border-b gap-1 rounded-t">
        <div className="relative h-8 mr-1">
          <button
            className={`px-2 py-1 bg-transparent rounded hover:border-[#B1B1B1] border border-transparent flex items-center ${showParagraphOptions ? 'border-[#B1B1B1]' : ''}`}
            title="段落スタイル"
            onClick={toggleParagraphMenu}
          >
            <span className="mr-3">
              {editor?.isActive('heading') ? (
                `H${
                  editor?.isActive('heading', { level: 1 })
                    ? '1'
                    : editor?.isActive('heading', { level: 2 })
                      ? '2'
                      : editor?.isActive('heading', { level: 3 })
                        ? '3'
                        : editor?.isActive('heading', { level: 4 })
                          ? '4'
                          : editor?.isActive('heading', { level: 5 })
                            ? '5'
                            : '6'
                }`
              ) : (
                <ParagraphIcon width={15} height={15} />
              )}
            </span>
            <Toggle width={10} height={10} />
          </button>
          <div
            ref={paragraphMenuRef}
            className={`absolute ${showParagraphOptions ? 'block' : 'hidden'} bg-white border rounded shadow-lg z-10 w-32`}
          >
            <button
              onClick={() => {
                setParagraph();
                setShowParagraphOptions(false);
              }}
              className={`w-full text-left px-3 py-1.5 hover:bg-gray-100 ${
                editor?.isActive('paragraph') ? 'bg-gray-200' : ''
              }`}
            >
              段落
            </button>
            <button
              onClick={() => {
                setHeading(1);
                setShowParagraphOptions(false);
              }}
              className={`w-full text-left px-3 py-1.5 hover:bg-gray-100 ${
                editor?.isActive('heading', { level: 1 }) ? 'bg-gray-200' : ''
              }`}
            >
              見出し 1
            </button>
            <button
              onClick={() => {
                setHeading(2);
                setShowParagraphOptions(false);
              }}
              className={`w-full text-left px-3 py-1.5 hover:bg-gray-100 ${
                editor?.isActive('heading', { level: 2 }) ? 'bg-gray-200' : ''
              }`}
            >
              見出し 2
            </button>
            <button
              onClick={() => {
                setHeading(3);
                setShowParagraphOptions(false);
              }}
              className={`w-full text-left px-3 py-1.5 hover:bg-gray-100 ${
                editor?.isActive('heading', { level: 3 }) ? 'bg-gray-200' : ''
              }`}
            >
              見出し 3
            </button>
          </div>
        </div>
        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        <div className="relative h-8 mr-1">
          <button
            className={`px-2 py-1 bg-transparent rounded hover:border-[#B1B1B1] border border-transparent flex items-center ${showFontSizeOptions ? 'border-[#B1B1B1]' : ''}`}
            title="フォントサイズ"
            onClick={toggleFontSizeMenu}
          >
            <TextFormat className="mr-3" width={22} height={22} />
            <Toggle width={10} height={10} />
          </button>
          <div
            ref={fontSizeMenuRef}
            className={`absolute ${showFontSizeOptions ? 'block' : 'hidden'} bg-white border rounded shadow-lg z-10 w-32`}
          >
            <button
              onClick={() => {
                setFontSize('12px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-xs"
            >
              小 (12px)
            </button>
            <button
              onClick={() => {
                setFontSize('16px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100"
            >
              中 (16px)
            </button>
            <button
              onClick={() => {
                setFontSize('20px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-lg"
            >
              大 (20px)
            </button>
            <button
              onClick={() => {
                setFontSize('24px');
                setShowFontSizeOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-xl"
            >
              特大 (24px)
            </button>
          </div>
        </div>

        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>

        <button
          onClick={toggleBold}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('bold') ? 'bg-gray-200' : ''
          }`}
          title="bold"
        >
          <BoldIcon width={16} height={16} />
        </button>
        <button
          onClick={toggleItalic}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('italic') ? 'bg-gray-200' : ''
          }`}
          title="italic"
        >
          <ItalicIcon width={16} height={16} />
        </button>
        <button
          onClick={toggleUnderline}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('underline') ? 'bg-gray-200' : ''
          }`}
          title="underline"
        >
          <UnderLineIcon width={16} height={16} />
        </button>
        <button
          onClick={toggleStrike}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('strike') ? 'bg-gray-200' : ''
          }`}
          title="strike"
        >
          <StrikeThrowIcon width={16} height={16} />
        </button>

        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>

        <button
          onClick={toggleBulletList}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('bulletList') ? 'bg-gray-200' : ''
          }`}
          title="bullet-list"
        >
          <BulletListIcon width={16} height={16} />
        </button>

        <button
          onClick={toggleOrderedList}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('orderedList') ? 'bg-gray-200' : ''
          }`}
          title="ordered-list"
        >
          <OrderedListIcon width={19} height={19} />
        </button>

        <button
          onClick={toggleBlockquote}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('blockquote') ? 'bg-gray-200' : ''
          }`}
          title="blockquote"
        >
          <QuoteIcon width={16} height={16} />
        </button>

        <button
          onClick={toggleCodeBlock}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent ${
            editor?.isActive('codeBlock') ? 'bg-gray-200' : ''
          }`}
          title="code block"
        >
          <CodeBlockIcon width={16} height={16} />
        </button>

        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>

        <button
          onClick={addImage}
          className={`bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent`}
          title="image"
        >
          <ImageIcon width={16} height={16} />
        </button>
      </div>

      <div className="flex rounded-b">
        <div className="w-10 text-[#B1B1B1] text-right py-2">
          {Array.from({ length: lineCount }, (_, i) => (
            <div key={i} className="h-6 pr-2 text-sm leading-6">
              {i + 1}
            </div>
          ))}
        </div>
        <div className="flex-grow pl-2 py-2" ref={editorRef}>
          <EditorContent editor={editor} className="outline-none w-full" />
        </div>
      </div>

      <style jsx global>{`
        .ProseMirror {
          outline: none;
          min-height: 200px;
        }
        .ProseMirror p {
          margin: 0;
          padding: 0;
          line-height: 1.5rem;
          min-height: 1.5rem;
        }
        .ProseMirror h1 {
          font-size: 1.75rem;
          margin: 0.75rem 0 0.25rem 0;
          font-weight: bold;
        }
        .ProseMirror h2 {
          font-size: 1.5rem;
          margin: 0.75rem 0 0.25rem 0;
          font-weight: bold;
        }
        .ProseMirror h3 {
          font-size: 1.25rem;
          margin: 0.5rem 0 0.25rem 0;
          font-weight: bold;
        }
        .ProseMirror.is-editor-empty:first-child::before {
          content: attr(data-placeholder);
          float: left;
          color: #666;
          pointer-events: none;
          height: 0;
        }
        .ProseMirror ul {
          padding-left: 1.5rem;
          margin: 0.5rem 0;
        }
        .ProseMirror ol {
          padding-left: 1.5rem;
          margin: 0.5rem 0;
        }
        .ProseMirror li {
          margin-bottom: 0.25rem;
        }
        .ProseMirror blockquote {
          border-left: 3px solid #ddd;
          padding-left: 1rem;
          margin-left: 0;
          margin-right: 0;
          color: #666;
        }
        .ProseMirror pre {
          background: #0D0D0D;
          color: #FFF;
          font-family: 'JetBrainsMono', monospace;
          padding: 0.75rem 1rem;
          border-radius: 0.5rem;
          margin: 0.5rem 0;
          overflow-x: auto;
          position: relative;
        }
        .ProseMirror pre::before {
          content: attr(data-language);
          position: absolute;
          top: 0.25rem;
          right: 0.5rem;
          font-size: 0.6rem;
          color: rgba(255, 255, 255, 0.5);
          text-transform: uppercase;
        }
        .ProseMirror .code-block {
          position: relative;
        }
        .ProseMirror .code-block::after {
          content: attr(data-language);
          position: absolute;
          top: 0;
          right: 0;
          padding: 0.25rem 0.5rem;
          font-size: 0.65rem;
          color: rgba(255, 255, 255, 0.5);
          background-color: rgba(0, 0, 0, 0.4);
          border-bottom-left-radius: 0.25rem;
          text-transform: uppercase;
        }
        .ProseMirror pre code {
          color: inherit;
          padding: 0;
          background: none;
          font-size: 0.8rem;
          font-family: 'JetBrainsMono', monospace;
          line-height: 1.5;
          display: block;
        }
        .ProseMirror code {
          background-color: rgba(97, 97, 97, 0.1);
          color: #616161;
          font-family: 'JetBrainsMono', monospace;
          border-radius: 0.25rem;
          padding: 0.15rem 0.25rem;
        }
        .ProseMirror img {
          max-width: 100%;
          height: auto;
          margin: 0.5rem 0;
        }
        /* スタイル付きテキスト */
        .ProseMirror span[style] {
          display: inline;
          white-space: pre-wrap;
        }
      `}</style>
    </div>
  );
};

export default TiptapEditor;
