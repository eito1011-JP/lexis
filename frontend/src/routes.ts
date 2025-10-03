export const ROUTES = {
  HOME: 'home',
  LOGIN: 'login',
  SIGNUP: 'signup',
  VERIFY_EMAIL: 'verify-email',
  ORGANIZATION_REGISTER: 'organization-register',
  ORGANIZATION_JOIN: 'organization-join',
  DOCUMENTS: 'documents',
  CREATE_DOCUMENT: 'create-document',
  CREATE_DOCUMENT_IN_CATEGORY: 'create-document-in-category',
  CREATE_CATEGORY: 'create-category',
  CREATE_ROOT_CATEGORY: 'create-root-category',
  EDIT_CATEGORY: 'edit-category',
  EDIT_DOCUMENT_IN_CATEGORY: 'edit-document-in-category',
  DOCUMENT_BY_SLUG: 'document-by-slug',
  CHANGE_SUGGESTIONS: 'change-suggestions',
  CHANGE_SUGGESTION_DETAIL: 'change-suggestion-detail',
  CHANGE_SUGGESTION_DIFF: 'change-suggestion-diff',
  CHANGE_SUGGESTION_CONFLICTS: 'change-suggestion-conflicts',
  CHANGE_SUGGESTION_FIX_REQUEST: 'change-suggestion-fix-request',
  CHANGE_SUGGESTION_EDIT_SESSION: 'change-suggestion-edit-session',
  USER_BRANCHES_DIFF: 'user-branches-diff',
} as const;

export const ROUTE_PATHS = {
  [ROUTES.HOME]: '/',
  [ROUTES.LOGIN]: '/login',
  [ROUTES.SIGNUP]: '/signup',
  [ROUTES.VERIFY_EMAIL]: '/verify-email',
  [ROUTES.ORGANIZATION_REGISTER]: '/organization/register',
  [ROUTES.ORGANIZATION_JOIN]: '/organization/join',
  [ROUTES.DOCUMENTS]: '/documents',
  [ROUTES.CREATE_DOCUMENT]: '/documents/create',
  [ROUTES.CREATE_DOCUMENT_IN_CATEGORY]: '/categories/:categoryEntityId/documents/create',
  [ROUTES.CREATE_CATEGORY]: '/categories/:categoryEntityId/create',
  [ROUTES.CREATE_ROOT_CATEGORY]: '/categories/create',
  [ROUTES.EDIT_CATEGORY]: '/categories/:categoryEntityId/edit',
  [ROUTES.EDIT_DOCUMENT_IN_CATEGORY]: '/categories/:categoryEntityId/documents/:documentEntityId/edit',
  [ROUTES.CHANGE_SUGGESTIONS]: '/change-suggestions',
  [ROUTES.CHANGE_SUGGESTION_DETAIL]: '/change-suggestions/:id',
  [ROUTES.CHANGE_SUGGESTION_DIFF]: '/change-suggestions/:id/diff',
  [ROUTES.CHANGE_SUGGESTION_CONFLICTS]: '/change-suggestions/:id/conflicts',
  [ROUTES.CHANGE_SUGGESTION_FIX_REQUEST]: '/change-suggestions/:id/fix-request',
  [ROUTES.CHANGE_SUGGESTION_EDIT_SESSION]:
    '/change-suggestions/:id/pull_request_edit_sessions/:token',
  [ROUTES.USER_BRANCHES_DIFF]: '/documents/diff',
} as const;

export const generatePath = (
  routeName: keyof typeof ROUTE_PATHS,
  params?: Record<string, string>
): string => {
  let path: string = ROUTE_PATHS[routeName];

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      path = path.replace(`:${key}`, value);
    });
  }
  return path;
};
