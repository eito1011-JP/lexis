import React from 'react';
import Head from '@docusaurus/Head';
import AdminHeader from './AdminHeader';
import AdminFooter from './AdminFooter';
import BranchStatusIndicator from '@site/src/components/admin/BranchStatusIndicator';

interface AdminLayoutProps {
  children: React.ReactNode;
  title?: string;
  description?: string;
}

export default function AdminLayout({
  children,
  title,
  description,
}: AdminLayoutProps): JSX.Element {
  const siteTitle = title ? `${title} | ハンドブック管理` : 'ハンドブック管理';
  const metaDescription = description || 'ハンドブック管理システム';

  return (
    <div className="min-h-screen flex flex-col bg-[#0A0A0A] text-gray-100">
      <Head>
        <title>{siteTitle}</title>
        <meta name="description" content={metaDescription} />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      </Head>

      <AdminHeader />
      
      <BranchStatusIndicator />

      <main className="flex-1 p-6">{children}</main>

      <AdminFooter />
    </div>
  );
} 