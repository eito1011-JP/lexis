import React from 'react';
import Layout from '@theme/Layout';

export default function AdminPage(): JSX.Element {
  const [content, setContent] = React.useState('<p>ここにドキュメントを作成してください...</p>');

  const handleEditorChange = (html: string) => {
    setContent(html);
    console.log('エディタの内容が更新されました:', html);
  };

  return (
    <Layout
      title="管理ページ"
      description="ドキュメント一覧"
    >
      <div className="container margin-vert--lg">
        <h1 className='text-blue-500'>ドキュメント</h1>
        <div className="row">
          
        </div>
      </div>
    </Layout>
  );
}
