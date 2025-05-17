import { Routes, Route } from 'react-router-dom';
import DocumentsPage from './pages/documents';
import DocumentBySlugPage from './pages/documents/slug';
import LoginPage from './pages/login';
import SignupPage from './pages/signup';
import CreateDocumentPage from './pages/documents/create';
import EditDocumentPage from './pages/documents/[slug]/edit';
import { ROUTE_PATHS } from './routes';
import { SessionProvider } from './contexts/SessionContext';

function App() {
  return (
    <SessionProvider>
      <Routes>
        <Route path={ROUTE_PATHS.home} element={<DocumentsPage />} />
        <Route path={ROUTE_PATHS.login} element={<LoginPage />} />
        <Route path={ROUTE_PATHS.signup} element={<SignupPage />} />
        <Route path={ROUTE_PATHS.documents} element={<DocumentsPage />} />
        <Route path={ROUTE_PATHS['create-document']} element={<CreateDocumentPage />} />
        <Route path={ROUTE_PATHS['edit-document']} element={<EditDocumentPage />} />
        <Route path={ROUTE_PATHS['document-by-slug']} element={<DocumentBySlugPage />} />
      </Routes>
    </SessionProvider>
  );
}

export default App;
