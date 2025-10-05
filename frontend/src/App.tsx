import { Routes, Route, Navigate } from 'react-router-dom';
import DocumentsPage from './pages/documents';
import OrganizationRegisterPage from './pages/organization/register';
import OrganizationJoinPage from './pages/organization/join.tsx';
import LoginPage from './pages/login';
import SignupPage from './pages/signup';
import CreateDocumentPage from './pages/documents/create';
import CreateRootCategoryPage from './pages/categories/create';
import EditCategoryPage from './pages/categories/[id]/edit';
import UserBranchDiffPage from './pages/documents/diff';
import EditDocumentInCategoryPage from './pages/categories/[id]/documents/edit';
import ChangeSuggestionsPage from './pages/change-suggestions';
import ChangeSuggestionDetailPage from './pages/change-suggestions/[id]';
import ChangeSuggestionDiffPage from './pages/change-suggestions/diff';
import FixRequestDetailPage from './pages/change-suggestions/[id]/fix-request';
import FixRequestDetailPageWithToken from './pages/change-suggestions/[id]/fix-request-detail';
import PullRequestEditSessionDetailPage from './pages/change-suggestions/[id]/pull_request_edit_sessions/[token]';
import ConflictResolutionPage from './pages/change-suggestions/[id]/conflicts';
import VerifyEmailPage from './pages/verify-email';
import { ROUTE_PATHS, ROUTES } from './routes';
import { ToastProvider } from './contexts/ToastContext';
import { AuthProvider, useAuth } from './contexts/AuthContext';

function ProtectedRoute({ children }: { children: JSX.Element }) {
  const { isAuthenticated, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="flex flex-col items-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          <p className="text-white">読み込み中...</p>
        </div>
      </div>
    );
  }
  
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

function PublicRoute({ children }: { children: JSX.Element }) {
  const { isAuthenticated, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="flex flex-col items-center">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          <p className="text-white">読み込み中...</p>
        </div>
      </div>
    );
  }
  
  if (isAuthenticated) {
    return <Navigate to="/documents" replace />;
  }
  return children;
}

function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <Routes>
        <Route path={ROUTE_PATHS.home} element={<ProtectedRoute><DocumentsPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS.login} element={<PublicRoute><LoginPage /></PublicRoute>} />
        <Route path={ROUTE_PATHS.signup} element={<PublicRoute><SignupPage /></PublicRoute>} />
        <Route path={ROUTE_PATHS['verify-email']} element={<PublicRoute><VerifyEmailPage /></PublicRoute>} />
        <Route path={ROUTE_PATHS['organization-register']} element={<PublicRoute><OrganizationRegisterPage /></PublicRoute>} />
        <Route path={ROUTE_PATHS['organization-join']} element={<PublicRoute><OrganizationJoinPage /></PublicRoute>} />
        <Route path={ROUTE_PATHS['change-suggestions']} element={<ProtectedRoute><ChangeSuggestionsPage /></ProtectedRoute>} />
        <Route
          path={ROUTE_PATHS['change-suggestion-detail']}
          element={<ProtectedRoute><ChangeSuggestionDetailPage /></ProtectedRoute>}
        />
        <Route
          path={ROUTE_PATHS['change-suggestion-diff']}
          element={<ProtectedRoute><ChangeSuggestionDiffPage /></ProtectedRoute>}
        />
        <Route
          path={ROUTE_PATHS['change-suggestion-conflicts']}
          element={<ProtectedRoute><ConflictResolutionPage /></ProtectedRoute>}
        />
        <Route
          path={ROUTE_PATHS['change-suggestion-fix-request']}
          element={<ProtectedRoute><FixRequestDetailPage /></ProtectedRoute>}
        />
        <Route
          path="/change-suggestions/:id/fix-request-detail"
          element={<ProtectedRoute><FixRequestDetailPageWithToken /></ProtectedRoute>}
        />
        <Route
          path="/change-suggestions/:id/pull_request_edit_sessions/:token"
          element={<ProtectedRoute><PullRequestEditSessionDetailPage /></ProtectedRoute>}
        />
        <Route path={ROUTE_PATHS.documents} element={<ProtectedRoute><DocumentsPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS['create-document']} element={<ProtectedRoute><CreateDocumentPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS['create-document-in-category']} element={<ProtectedRoute><CreateDocumentPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS['create-category']} element={<ProtectedRoute><CreateRootCategoryPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS['create-root-category']} element={<ProtectedRoute><CreateRootCategoryPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS['edit-category']} element={<ProtectedRoute><EditCategoryPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS['edit-document-in-category']} element={<ProtectedRoute><EditDocumentInCategoryPage /></ProtectedRoute>} />
        <Route path={ROUTE_PATHS[ROUTES.USER_BRANCHES_DIFF]} element={<ProtectedRoute><UserBranchDiffPage /></ProtectedRoute>} />
        </Routes>
      </ToastProvider>
    </AuthProvider>
  );
}

export default App;
