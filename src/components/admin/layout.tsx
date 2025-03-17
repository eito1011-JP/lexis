import React from 'react';
import Head from '@docusaurus/Head';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Header from './header/layout';

/**
 * 管理画面用のレイアウトコンポーネント (Tailwind CSS使用)
 */
interface AdminLayoutProps {
  children: React.ReactNode;
  title: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps): JSX.Element {
  const {siteConfig} = useDocusaurusContext();
  
  return (
    <>
      <Head>
        <title>{title} | {siteConfig.title}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      </Head>
      <div className="min-h-screen flex flex-col">
        <Header />
      </div>
    </>
  );
}
