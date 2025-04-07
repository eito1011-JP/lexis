import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import { EditorContent, useEditor } from '@tiptap/react';
import React, { useEffect, useRef, useState } from 'react';

interface TiptapEditorProps {
  initialContent: string;
  onChange: (html: string) => void;
}

const TiptapEditor: React.FC<TiptapEditorProps> = ({ initialContent, onChange }) => {
  const [lineCount, setLineCount] = useState<number>(1);
  const editorRef = useRef<HTMLDivElement>(null);

  const editor = useEditor({
    extensions: [Document, Paragraph, Text],
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

  return (
    <div className="flex w-full font-mono relative">
      <div className="w-10 pt-2 bg-gray-800 text-gray-500 text-right select-none border-r border-gray-700">
        {Array.from({ length: lineCount }, (_, i) => (
          <div key={i} className="h-6 pr-2 text-sm leading-6">
            {i + 1}
          </div>
        ))}
      </div>
      <div className="flex-grow pl-2" ref={editorRef}>
        <EditorContent editor={editor} className="outline-none" />
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
      `}</style>
    </div>
  );
};

export default TiptapEditor;
