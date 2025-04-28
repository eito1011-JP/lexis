import { Routes, Route } from 'react-router-dom';
import DocumentsPage from './pages/documents';
import DocumentDetailPage from './pages/documents/slug';
import LoginPage from './pages/login';
import SignupPage from './pages/signup';
import CreateDocumentPage from './pages/documents/create';
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
