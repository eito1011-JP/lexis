import React from 'react';
import { useLocation } from '@docusaurus/router';
import OriginalNavbar from '@theme-original/Navbar';
import type NavbarType from '@theme/Navbar';
import type { WrapperProps } from '@docusaurus/types';

type Props = WrapperProps<typeof NavbarType>;

/**
 * カスタムNavbarコンポーネント
 * /admin パスではナビゲーションバーを非表示にする
 */
export default function NavbarWrapper(props: Props): JSX.Element | null {
  const location = useLocation();
  
  // /admin パスではナビゲーションバーを表示しない
  if (location.pathname.startsWith('/admin')) {
    return null;
  }
  
  // それ以外のパスでは通常のナビゲーションバーを表示
  return <OriginalNavbar {...props} />;
}
