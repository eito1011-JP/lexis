import React, { useState, useRef } from 'react';
import './styles.css';
import { Bold as BoldIcon } from '../../icon/editor/Bold';
import { Italic as ItalicIcon } from '../../icon/editor/Italic';
import { UnderLine as UnderLineIcon } from '../../icon/editor/UnderLine';
import { BulletList as BulletListIcon } from '../../icon/editor/BulletList';
import { StrikeThrow as StrikeThrowIcon } from '../../icon/editor/StrikeThrow';
import { Quote as QuoteIcon } from '../../icon/editor/Quote';
import { OrderedList as OrderedListIcon } from '../../icon/editor/OrderedList';
import { CodeBlock as CodeBlockIcon } from '../../icon/editor/CodeBlock';
import { Image as ImageIcon } from '../../icon/common/Image';
import { Paragraph as ParagraphIcon } from '../../icon/editor/Paragraph';
import { LineBreak as LineBreakIcon } from '../../icon/editor/LineBreak';
import Toggle from '../../icon/editor/Toggle';

interface MarkdownEditorProps {
  initialContent: string;
  onChange: (html: string) => void;
  onMarkdownChange?: (markdown: string) => void;
  placeholder?: string;
}

const MarkdownEditor: React.FC<MarkdownEditorProps> = ({
  initialContent,
  onChange,
  onMarkdownChange,
  placeholder = 'ここにMarkdownでドキュメントを作成してください',
}) => {
  const [showParagraphOptions, setShowParagraphOptions] = useState(false);
  const [markdown, setMarkdown] = useState(initialContent || '');
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const lineNumbersRef = useRef<HTMLDivElement>(null);

  // 行番号を計算
  const getLineNumbers = () => {
    const lines = markdown.split('\n');
    return lines.map((_, index) => index + 1);
  };

  // テキストエリアとの同期スクロール
  const handleTextareaScroll = () => {
    const textarea = textareaRef.current;
    const lineNumbers = lineNumbersRef.current;

    if (textarea && lineNumbers) {
      lineNumbers.scrollTop = textarea.scrollTop;
    }
  };

  // Markdown→HTML変換（改良版）
  const markdownToHtml = (markdown: string): string => {
    if (!markdown.trim()) return '';

    // まず全体の改行処理を先に行う
    let processedMarkdown = markdown.replace(/  \n/g, '<LINEBREAK>');

    const lines = processedMarkdown.split('\n');
    const htmlLines: string[] = [];
    let inList = false;
    let inOrderedList = false;
    let inCodeBlock = false;
    let inBlockquote = false;
    let codeBlockContent: string[] = [];
    let blockquoteContent = '';

    for (let i = 0; i < lines.length; i++) {
      let line = lines[i];

      // コードブロックの処理
      if (line.startsWith('```')) {
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        if (inCodeBlock) {
          htmlLines.push(`<pre><code>${codeBlockContent.join('\n')}</code></pre>`);
          codeBlockContent = [];
          inCodeBlock = false;
        } else {
          inCodeBlock = true;
        }
        continue;
      }

      if (inCodeBlock) {
        codeBlockContent.push(line);
        continue;
      }

      // 空行の処理
      if (!line.trim()) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        htmlLines.push('');
        continue;
      }

      // 見出しの処理
      if (line.startsWith('### ')) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        htmlLines.push(`<h3>${processInlineElements(line.substring(4))}</h3>`);
        continue;
      }
      if (line.startsWith('## ')) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        htmlLines.push(`<h2>${processInlineElements(line.substring(3))}</h2>`);
        continue;
      }
      if (line.startsWith('# ')) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        htmlLines.push(`<h1>${processInlineElements(line.substring(2))}</h1>`);
        continue;
      }

      // 引用の処理
      if (line.startsWith('> ')) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }

        if (!inBlockquote) {
          // 引用ブロックを開始
          inBlockquote = true;
          blockquoteContent = `<p>${processInlineElements(line.substring(2))}</p>`;
        } else {
          // 既存の引用ブロックに追加
          blockquoteContent += `<p>${processInlineElements(line.substring(2))}</p>`;
        }
        continue;
      }

      // 箇条書きリストの処理
      if (line.match(/^[-*+]\s+/)) {
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        if (!inList) {
          htmlLines.push('<ul>');
          inList = true;
        }
        const listItemContent = line.replace(/^[-*+]\s+/, '').trim();
        htmlLines.push(`<li>${processInlineElements(listItemContent)}</li>`);
        continue;
      }

      // 番号付きリストの処理
      if (line.match(/^\d+\.\s+/)) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        if (!inOrderedList) {
          htmlLines.push('<ol>');
          inOrderedList = true;
        }
        const listItemContent = line.replace(/^\d+\.\s+/, '').trim();
        htmlLines.push(`<li>${processInlineElements(listItemContent)}</li>`);
        continue;
      }

      // 引用ブロックが終了した場合
      if (inBlockquote && !line.startsWith('> ')) {
        htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
        inBlockquote = false;
        blockquoteContent = '';
      }

      // 通常の段落
      if (line.trim()) {
        if (inList) {
          htmlLines.push('</ul>');
          inList = false;
        }
        if (inOrderedList) {
          htmlLines.push('</ol>');
          inOrderedList = false;
        }
        // 引用ブロックが終了した場合
        if (inBlockquote) {
          htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
          inBlockquote = false;
          blockquoteContent = '';
        }
        htmlLines.push(`<p>${processInlineElements(line)}</p>`);
      }
    }

    // リストが閉じられていない場合
    if (inList) htmlLines.push('</ul>');
    if (inOrderedList) htmlLines.push('</ol>');

    // 引用ブロックが閉じられていない場合
    if (inBlockquote) {
      htmlLines.push(`<blockquote>${blockquoteContent}</blockquote>`);
    }

    return htmlLines.join('\n');
  };

  // インライン要素の処理
  const processInlineElements = (text: string): string => {
    // 最初にHTMLエスケープ（ただしプレースホルダーは保護）
    let escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
      // プレースホルダーを元に戻す
      .replace(/&lt;LINEBREAK&gt;/g, '<LINEBREAK>');

    return (
      escaped
        // 太字
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        // 斜体
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        // 下線（マークダウンの __text__ 形式）
        .replace(/__(.+?)__/g, '<u>$1</u>')
        // 取り消し線
        .replace(/~~(.+?)~~/g, '<s>$1</s>')
        // インラインコード
        .replace(/`(.+?)`/g, '<code>$1</code>')
        // リンク
        .replace(
          /\[([^\]]+)\]\(([^)]+)\)/g,
          '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
        )
        // 画像
        .replace(
          /!\[([^\]]*)\]\(([^)]+)\)/g,
          '<img src="$2" alt="$1" style="max-width: 100%; height: auto;" />'
        )
        // 最後にプレースホルダーを<br>に変換
        .replace(/<LINEBREAK>/g, '<br>')
    );
  };

  // マークダウンの変更を処理
  const handleMarkdownChange = (event: React.ChangeEvent<HTMLTextAreaElement>) => {
    const newMarkdown = event.target.value;
    setMarkdown(newMarkdown);

    // HTMLに変換して親コンポーネントに通知
    const html = markdownToHtml(newMarkdown);
    onChange(html);

    // マークダウンも通知
    if (onMarkdownChange) {
      onMarkdownChange(newMarkdown);
    }
  };

  // キーダウンイベントを処理（改行をサポート）
  const handleKeyDown = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Enterキーの場合、デフォルトの改行動作を許可
    if (event.key === 'Enter') {
      // デフォルトの改行動作を妨げない
      return;
    }
  };

  // テキストエリアにマークダウン構文を挿入
  const insertMarkdown = (syntax: string, placeholder = '') => {
    const textarea = textareaRef.current;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = markdown.substring(start, end);

    let newText = '';
    let newCursorPos = start;

    switch (syntax) {
      case 'heading-one':
        newText = `# ${selectedText || placeholder}`;
        newCursorPos = start + 2;
        break;
      case 'heading-two':
        newText = `## ${selectedText || placeholder}`;
        newCursorPos = start + 3;
        break;
      case 'heading-three':
        newText = `### ${selectedText || placeholder}`;
        newCursorPos = start + 4;
        break;
      case 'bold':
        if (selectedText.includes('\n')) {
          // 複数行の場合、各行に太字を適用
          const lines = selectedText.split('\n');
          const processedLines = lines.map(line => (line.trim() ? `**${line}**` : line));
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else {
          newText = `**${selectedText || placeholder}**`;
          newCursorPos = selectedText ? start : start + 2;
        }
        break;
      case 'italic':
        if (selectedText.includes('\n')) {
          // 複数行の場合、各行に斜体を適用
          const lines = selectedText.split('\n');
          const processedLines = lines.map(line => (line.trim() ? `*${line}*` : line));
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else {
          newText = `*${selectedText || placeholder}*`;
          newCursorPos = selectedText ? start : start + 1;
        }
        break;
      case 'underline':
        if (selectedText.includes('\n')) {
          // 複数行の場合、各行に下線を適用
          const lines = selectedText.split('\n');
          const processedLines = lines.map(line => (line.trim() ? `__${line}__` : line));
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else {
          newText = `__${selectedText || placeholder}__`;
          newCursorPos = selectedText ? start : start + 2;
        }
        break;
      case 'strike':
        if (selectedText.includes('\n')) {
          // 複数行の場合、各行に取り消し線を適用
          const lines = selectedText.split('\n');
          const processedLines = lines.map(line => (line.trim() ? `~~${line}~~` : line));
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else {
          newText = `~~${selectedText || placeholder}~~`;
          newCursorPos = selectedText ? start : start + 2;
        }
        break;
      case 'code':
        if (selectedText.includes('\n')) {
          // 複数行の場合はコードブロックとして処理
          newText = `\`\`\`\n${selectedText || placeholder}\n\`\`\``;
          newCursorPos = selectedText ? start : start + 4;
        } else {
          newText = `\`${selectedText || placeholder}\``;
          newCursorPos = start + 1;
        }
        break;
      case 'block-quote': {
        if (selectedText.includes('\n')) {
          // 複数行が選択されている場合、各行に引用記号を追加
          const lines = selectedText.split('\n');
          const processedLines = lines.map(line => (line.trim() ? `> ${line.trim()}` : '> '));
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else if (selectedText.trim()) {
          // 単一行のテキストが選択されている場合
          newText = `> ${selectedText.trim()}`;
          newCursorPos = start;
        } else {
          // テキストが選択されていない場合、現在の位置に引用文を挿入
          const currentLineStart = markdown.lastIndexOf('\n', start - 1) + 1;
          const currentLine = markdown.substring(currentLineStart, start);
          if (currentLine.trim() !== '') {
            newText = `\n> ${placeholder}`;
            newCursorPos = start + 3;
          } else {
            newText = `> ${placeholder}`;
            newCursorPos = start + 2;
          }
        }
        break;
      }
      case 'bulleted-list': {
        if (selectedText.includes('\n')) {
          // 複数行が選択されている場合、各行をリストアイテムに変換
          const lines = selectedText.split('\n');
          const processedLines = lines.map(line => (line.trim() ? `- ${line.trim()}` : ''));
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else if (selectedText.trim()) {
          // 単一行のテキストが選択されている場合
          newText = `- ${selectedText.trim()}`;
          newCursorPos = start;
        } else {
          // テキストが選択されていない場合、現在の位置にリストアイテムを挿入
          const currentLineStart = markdown.lastIndexOf('\n', start - 1) + 1;
          const currentLine = markdown.substring(currentLineStart, start);
          if (currentLine.trim() !== '') {
            newText = `\n- ${placeholder}`;
            newCursorPos = start + 3;
          } else {
            newText = `- ${placeholder}`;
            newCursorPos = start + 2;
          }
        }
        break;
      }
      case 'numbered-list': {
        if (selectedText.includes('\n')) {
          // 複数行が選択されている場合、各行を番号付きリストアイテムに変換
          const lines = selectedText.split('\n');
          let counter = 1;
          const processedLines = lines.map(line =>
            line.trim() ? `${counter++}. ${line.trim()}` : ''
          );
          newText = processedLines.join('\n');
          newCursorPos = start;
        } else if (selectedText.trim()) {
          // 単一行のテキストが選択されている場合
          newText = `1. ${selectedText.trim()}`;
          newCursorPos = start;
        } else {
          // テキストが選択されていない場合、現在の位置にリストアイテムを挿入
          const currentLineStart = markdown.lastIndexOf('\n', start - 1) + 1;
          const currentLine = markdown.substring(currentLineStart, start);
          if (currentLine.trim() !== '') {
            newText = `\n1. ${placeholder}`;
            newCursorPos = start + 4;
          } else {
            newText = `1. ${placeholder}`;
            newCursorPos = start + 3;
          }
        }
        break;
      }
      case 'link': {
        const url = prompt('リンクURLを入力してください:') || '#';
        newText = `[${selectedText || placeholder}](${url})`;
        newCursorPos = selectedText ? start : start + 1;
        break;
      }
      case 'image': {
        const imageUrl = prompt('画像URLを入力してください:') || '#';
        newText = `![${selectedText || 'alt text'}](${imageUrl})`;
        newCursorPos = selectedText ? start : start + 2;
        break;
      }
      case 'line-break': {
        // 改行を挿入（マークダウンの改行記法：行末に2つのスペース + 改行）
        if (selectedText) {
          // テキストが選択されている場合、選択されたテキストの後に改行を追加
          newText = selectedText + '  \n';
          newCursorPos = start + newText.length;
        } else {
          // テキストが選択されていない場合、現在の位置に改行を挿入
          newText = '  \n';
          newCursorPos = start + newText.length;
        }
        break;
      }
      default:
        return;
    }

    const newMarkdown = markdown.substring(0, start) + newText + markdown.substring(end);
    setMarkdown(newMarkdown);

    // HTMLに変換して親コンポーネントに通知
    const html = markdownToHtml(newMarkdown);
    onChange(html);

    if (onMarkdownChange) {
      onMarkdownChange(newMarkdown);
    }

    // カーソル位置を設定
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(
        newCursorPos,
        newCursorPos + (placeholder ? placeholder.length : 0)
      );
    }, 0);
  };

  // UI
  return (
    <div className="w-full relative markdown-editor">
      <div className="flex mb-2 pb-5 pt-1 px-1 border-b gap-1 rounded-t">
        {/* 段落・見出し */}
        <div className="relative h-8">
          <button
            className={`h-8 rounded hover:border-[#B1B1B1] border border-transparent px-2 ${showParagraphOptions ? 'border-[#B1B1B1]' : ''}`}
            title="見出しスタイル"
            onClick={() => {
              setShowParagraphOptions(!showParagraphOptions);
            }}
          >
            <div className="flex items-center gap-1">
              <ParagraphIcon width={15} height={15} />
              <Toggle width={9} height={9} />
            </div>
          </button>
          <div
            className={`absolute ${showParagraphOptions ? 'block' : 'hidden'} bg-white border border-gray-300 rounded shadow-lg z-10 w-32 mt-1`}
          >
            <button
              onClick={() => {
                insertMarkdown('heading-one', '見出し1');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-gray-800 border-b border-gray-200 first:rounded-t last:rounded-b last:border-b-0"
            >
              # 見出し 1
            </button>
            <button
              onClick={() => {
                insertMarkdown('heading-two', '見出し2');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-gray-800 border-b border-gray-200 first:rounded-t last:rounded-b last:border-b-0"
            >
              ## 見出し 2
            </button>
            <button
              onClick={() => {
                insertMarkdown('heading-three', '見出し3');
                setShowParagraphOptions(false);
              }}
              className="w-full text-left px-3 py-1.5 hover:bg-gray-100 text-gray-800 border-b border-gray-200 first:rounded-t last:rounded-b last:border-b-0"
            >
              ### 見出し 3
            </button>
          </div>
        </div>
        <div className="flex items-center h-8 mx-0.5">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* マーク */}
        <button
          onClick={() => insertMarkdown('bold', '太字')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="太字 (**text**)"
        >
          <BoldIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('italic', '斜体')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="斜体 (*text*)"
        >
          <ItalicIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('underline', '下線')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="下線 (__text__)"
        >
          <UnderLineIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('strike', '取り消し線')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="取り消し線 (~~text~~)"
        >
          <StrikeThrowIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('line-break')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="改行 (行末に2つのスペース + 改行)"
        >
          <LineBreakIcon width={16} height={16} />
        </button>
        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* リスト・引用・コード */}
        <button
          onClick={() => insertMarkdown('bulleted-list', 'リストアイテム')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="箇条書きリスト (- text)"
        >
          <BulletListIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('numbered-list', 'リストアイテム')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="番号付きリスト (1. text)"
        >
          <OrderedListIcon width={19} height={19} />
        </button>
        <button
          onClick={() => insertMarkdown('block-quote', '引用文')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="引用 (> text)"
        >
          <QuoteIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('code', 'コード')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="コード (`code` または ```code```)"
        >
          <CodeBlockIcon width={16} height={16} />
        </button>
        <div className="flex items-center h-8 mx-1">
          <div className="h-5 border-l border-[#B1B1B1]"></div>
        </div>
        {/* 画像・リンク */}
        <button
          onClick={() => insertMarkdown('image', 'alt text')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="画像 (![alt](url))"
        >
          <ImageIcon width={16} height={16} />
        </button>
        <button
          onClick={() => insertMarkdown('link', 'リンクテキスト')}
          className="bg-transparent px-2 py-1 rounded hover:border-[#B1B1B1] border border-transparent"
          title="リンク ([text](url))"
        >
          🔗
        </button>
      </div>
      <div className="rounded-b">
        <div className="w-full pt-4 flex">
          {/* 左側: Markdownエディター */}
          <div className="flex-1 flex">
            {/* 行番号 */}
            <div
              ref={lineNumbersRef}
              className="line-numbers flex-shrink-0 text-gray-500 text-sm font-mono overflow-hidden"
              style={{
                width: '50px',
                minHeight: '400px',
                overflow: 'hidden',
              }}
            >
              {getLineNumbers().map(lineNumber => (
                <div
                  key={lineNumber}
                  className="line-number px-2 text-right"
                  style={{
                    height: '1.4em',
                    lineHeight: '1.4em',
                    whiteSpace: 'nowrap',
                  }}
                >
                  {lineNumber}
                </div>
              ))}
            </div>

            {/* テキストエリア */}
            <textarea
              ref={textareaRef}
              value={markdown}
              onChange={handleMarkdownChange}
              onKeyDown={handleKeyDown}
              onScroll={handleTextareaScroll}
              placeholder={placeholder}
              className="outline-none flex-1 min-h-[400px] resize-none font-mono text-sm border-0 focus:ring-0"
              style={{
                lineHeight: '1.4em',
              }}
              spellCheck={false}
            />
          </div>

          {/* 右側: プレビュー */}
          <div className="flex-1 pl-4">
            <div
              className="min-h-[400px] overflow-auto markdown-preview"
              dangerouslySetInnerHTML={{ __html: markdownToHtml(markdown) }}
            />
          </div>
        </div>
      </div>
    </div>
  );
};

export default MarkdownEditor;
