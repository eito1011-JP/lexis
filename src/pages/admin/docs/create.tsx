import React from 'react';
import Layout from '@theme/Layout';
import TiptapEditor from '@site/src/components/admin/editor/TiptapEditor';

export default function AdminPage(): JSX.Element {
  const [content, setContent] = React.useState('<p>ここにドキュメントを作成してください...</p>');

  const handleEditorChange = (html: string) => {
    setContent(html);
    console.log('エディタの内容が更新されました:', html);
  };

  return (
    <Layout
      title="管理ページ"
      description="ドキュメント管理ページ"
    >
      <div className="container margin-vert--lg">
        <h1>ドキュメント管理</h1>
        <div className="row">
          <div className="col col--8">
            <div className="card">
              <div className="card__header">
                <h2>エディタ</h2>
              </div>
              <div className="card__body">
                <TiptapEditor 
                  initialContent={content} 
                  onChange={handleEditorChange} 
                />
              </div>
            </div>
          </div>
          <div className="col col--4">
            <div className="card">
              <div className="card__header">
                <h2>プレビュー</h2>
              </div>
              <div className="card__body">
                <div dangerouslySetInnerHTML={{ __html: content }} />
              </div>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}
