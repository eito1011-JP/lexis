import React from 'react';
import { Home } from '@/components/icon/common/Home';
import { type BreadcrumbItem } from '@/api/category';

interface BreadcrumbProps {
  breadcrumbs?: BreadcrumbItem[];
  categoryPath?: string | null;
  homeLink?: string;
  className?: string;
}

/**
 * パンくずリストコンポーネント
 * ホームアイコンから始まり、各カテゴリを「>」で区切って表示
 */
export const Breadcrumb: React.FC<BreadcrumbProps> = ({
  breadcrumbs,
  categoryPath,
  homeLink = "/documents",
  className = "flex items-center text-sm text-gray-400"
}) => {
  // categoryPathが指定されている場合は、それをBreadcrumbItemの配列に変換
  const getBreadcrumbsFromPath = (path: string | null | undefined): BreadcrumbItem[] => {
    if (!path) return [];
    
    const pathParts = path.split('/').filter(part => part.length > 0);
    return pathParts.map((part, index) => ({
      id: index + 1,
      title: part
    }));
  };

  const finalBreadcrumbs = breadcrumbs || getBreadcrumbsFromPath(categoryPath);

  return (
    <div className={className}>
      <a href={homeLink} className="hover:text-white">
        <Home className="w-4 h-4 ml-0 mr-2" />
      </a>
      
      {finalBreadcrumbs && finalBreadcrumbs.length > 0 && (
        <>
          {finalBreadcrumbs.map((breadcrumb) => (
            <span key={breadcrumb.id}>
              <span className="mx-2">{'>'}</span>
              <span className="text-white">{breadcrumb.title}</span>
            </span>
          ))}
        </>
      )}
    </div>
  );
};
