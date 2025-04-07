import React from 'react';
import { SessionProvider } from '@site/src/contexts/SessionContext';

export default function Root({ children }) {
  return <SessionProvider>{children}</SessionProvider>;
}
