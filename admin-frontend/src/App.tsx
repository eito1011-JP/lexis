import React from 'react';
import { Routes, Route } from 'react-router-dom';
import DocumentsPage from './pages/admin/documents';
import DocumentDetailPage from './pages/admin/documents/slug';
import LoginPage from './pages/admin/login';
import SignupPage from './pages/admin/signup';
import CreateDocumentPage from './pages/admin/documents/create';

function App() {
  return (
    <Routes>
      <Route path="/" element={<DocumentsPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/signup" element={<SignupPage />} />
      <Route path="/documents" element={<DocumentsPage />} />
      <Route path="/documents/create" element={<CreateDocumentPage />} />
      <Route path="/documents/:slug" element={<DocumentDetailPage />} />
    </Routes>
  );
}

export default App; 