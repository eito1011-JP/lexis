import { Routes, Route } from 'react-router-dom';
import DocumentsPage from './pages/documents';
import OrganizationRegisterPage from './pages/organization/register';
import OrganizationJoinPage from './pages/organization/join.tsx';
import DocumentBySlugPage from './pages/documents/slug';
import LoginPage from './pages/login';
import SignupPage from './pages/signup';
import CreateDocumentPage from './pages/documents/create';
import EditDocumentPage from './pages/documents/[slug]/edit';
import ChangeSuggestionsPage from './pages/change-suggestions';
import ChangeSuggestionDetailPage from './pages/change-suggestions/[id]';
import ChangeSuggestionDiffPage from './pages/change-suggestions/diff';
import FixRequestDetailPage from './pages/change-suggestions/[id]/fix-request';
import FixRequestDetailPageWithToken from './pages/change-suggestions/[id]/fix-request-detail';
import PullRequestEditSessionDetailPage from './pages/change-suggestions/[id]/pull_request_edit_sessions/[token]';
import ConflictResolutionPage from './pages/change-suggestions/[id]/conflicts';
import VerifyEmailPage from './pages/verify-email';
import { ROUTE_PATHS } from './routes';
import { SessionProvider } from './contexts/SessionContext';

function App() {
  return (
    <SessionProvider>
      <Routes>
        <Route path={ROUTE_PATHS.home} element={<DocumentsPage />} />
        <Route path={ROUTE_PATHS.login} element={<LoginPage />} />
        <Route path={ROUTE_PATHS.signup} element={<SignupPage />} />
        <Route path={ROUTE_PATHS['verify-email']} element={<VerifyEmailPage />} />
        <Route path={ROUTE_PATHS['organization-register']} element={<OrganizationRegisterPage />} />
        <Route path={ROUTE_PATHS['organization-join']} element={<OrganizationJoinPage />} />
        <Route path={ROUTE_PATHS['change-suggestions']} element={<ChangeSuggestionsPage />} />
        <Route
          path={ROUTE_PATHS['change-suggestion-detail']}
          element={<ChangeSuggestionDetailPage />}
        />
        <Route
          path={ROUTE_PATHS['change-suggestion-diff']}
          element={<ChangeSuggestionDiffPage />}
        />
        <Route
          path={ROUTE_PATHS['change-suggestion-conflicts']}
          element={<ConflictResolutionPage />}
        />
        <Route
          path={ROUTE_PATHS['change-suggestion-fix-request']}
          element={<FixRequestDetailPage />}
        />
        <Route
          path="/change-suggestions/:id/fix-request-detail"
          element={<FixRequestDetailPageWithToken />}
        />
        <Route
          path="/change-suggestions/:id/pull_request_edit_sessions/:token"
          element={<PullRequestEditSessionDetailPage />}
        />
        <Route path={ROUTE_PATHS.documents} element={<DocumentsPage />} />
        <Route path={ROUTE_PATHS['create-document']} element={<CreateDocumentPage />} />
        <Route path={ROUTE_PATHS['edit-document']} element={<EditDocumentPage />} />
        <Route path={ROUTE_PATHS['document-by-slug']} element={<DocumentBySlugPage />} />
      </Routes>
    </SessionProvider>
  );
}

export default App;
