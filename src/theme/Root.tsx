import React from 'react';
import { SessionProvider } from '@site/admin-frontend/src/contexts/SessionContext';

export default function Root({ children }) {
  return <SessionProvider>{children}</SessionProvider>;
}
