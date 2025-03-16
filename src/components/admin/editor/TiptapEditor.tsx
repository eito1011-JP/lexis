import './styles.css'

import Document from '@tiptap/extension-document'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import { EditorContent, useEditor } from '@tiptap/react'
import React from 'react'

interface TiptapEditorProps {
  initialContent: string;
  onChange: (html: string) => void;
}

const TiptapEditor: React.FC<TiptapEditorProps> = ({ initialContent, onChange }) => {
  const editor = useEditor({
    extensions: [
      Document,
      Paragraph,
      Text,
    ],
    content: initialContent,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
  })

  return (
    <EditorContent editor={editor} />
  )
}

export default TiptapEditor