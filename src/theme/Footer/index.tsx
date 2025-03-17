import React from 'react';
import { useLocation } from '@docusaurus/router';
import OriginalFooter from '@theme-original/Footer';
import type FooterType from '@theme/Footer';
import type { WrapperProps } from '@docusaurus/types';

type Props = WrapperProps<typeof FooterType>;

/**
 * カスタムFooterコンポーネント
 * /admin パスではフッターを非表示にする
 */
export default function FooterWrapper(props: Props): JSX.Element | null {
  const location = useLocation();
  
  // /admin パスではフッターを表示しない
  if (location.pathname.startsWith('/admin')) {
    return null;
  }
  
  // それ以外のパスでは通常のフッターを表示
  return <OriginalFooter {...props} />;
}
