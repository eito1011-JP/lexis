import React, { createContext, useCallback, useContext, useState } from 'react';
import { Toast } from '@/components/admin/Toast';

type ToastType = 'success' | 'error';

type ToastPayload = {
  message: string;
  type: ToastType;
};

type ToastContextValue = {
  show: (payload: ToastPayload) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

export function ToastProvider({ children }: { children: React.ReactNode }): React.ReactElement {
  const [toastPayload, setToastPayload] = useState<ToastPayload | null>(null);

  const show = useCallback((payload: ToastPayload) => {
    setToastPayload(payload);
  }, []);

  return (
    <ToastContext.Provider value={{ show }}>
      {children}
      {toastPayload && (
        <Toast
          message={toastPayload.message}
          type={toastPayload.type}
          onClose={() => setToastPayload(null)}
        />
      )}
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext);
  if (!ctx) {
    throw new Error('useToast must be used within ToastProvider');
  }
  return ctx;
}


