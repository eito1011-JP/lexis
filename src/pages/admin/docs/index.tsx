import AdminLayout from '@site/src/components/admin/layout';
import React, { useState } from 'react';

/**
 * 管理画面のメインページコンポーネント
 */
export default function AdminPage(): JSX.Element {
  const [content, setContent] = useState('<p>ここにドキュメントを作成してください...</p>');

  const handleEditorChange = (html: string) => {
    setContent(html);
    console.log('エディタの内容が更新されました:', html);
  };

  return (
    <AdminLayout title="管理ページ">
        <h1 className="text-2xl font-bold text-blue-500 mb-6">ドキュメント</h1>
    </AdminLayout>
  );
}
