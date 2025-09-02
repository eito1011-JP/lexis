export const ROUTES = {
  HOME: 'home',
  LOGIN: 'login',
  SIGNUP: 'signup',
  ORGANIZATION_REGISTER: 'organization-register',
  ORGANIZATION_JOIN: 'organization-join',
  DOCUMENTS: 'documents',
  CREATE_DOCUMENT: 'create-document',
  EDIT_DOCUMENT: 'edit-document',
  DOCUMENT_BY_SLUG: 'document-by-slug',
  CHANGE_SUGGESTIONS: 'change-suggestions',
  CHANGE_SUGGESTION_DETAIL: 'change-suggestion-detail',
  CHANGE_SUGGESTION_DIFF: 'change-suggestion-diff',
  CHANGE_SUGGESTION_CONFLICTS: 'change-suggestion-conflicts',
  CHANGE_SUGGESTION_FIX_REQUEST: 'change-suggestion-fix-request',
  CHANGE_SUGGESTION_EDIT_SESSION: 'change-suggestion-edit-session',
} as const;

export const ROUTE_PATHS = {
  [ROUTES.HOME]: '/',
  [ROUTES.LOGIN]: '/login',
  [ROUTES.SIGNUP]: '/signup',
  [ROUTES.ORGANIZATION_REGISTER]: '/organization/register',
  [ROUTES.ORGANIZATION_JOIN]: '/organization/join',
  [ROUTES.DOCUMENTS]: '/documents',
  [ROUTES.CREATE_DOCUMENT]: '/documents/create',
  [ROUTES.EDIT_DOCUMENT]: '/documents/**/edit',
  [ROUTES.DOCUMENT_BY_SLUG]: '/documents/:paths/*',
  [ROUTES.CHANGE_SUGGESTIONS]: '/change-suggestions',
  [ROUTES.CHANGE_SUGGESTION_DETAIL]: '/change-suggestions/:id',
  [ROUTES.CHANGE_SUGGESTION_DIFF]: '/change-suggestions/:id/diff',
  [ROUTES.CHANGE_SUGGESTION_CONFLICTS]: '/change-suggestions/:id/conflicts',
  [ROUTES.CHANGE_SUGGESTION_FIX_REQUEST]: '/change-suggestions/:id/fix-request',
  [ROUTES.CHANGE_SUGGESTION_EDIT_SESSION]:
    '/change-suggestions/:id/pull_request_edit_sessions/:token',
} as const;

export const generatePath = (
  routeName: keyof typeof ROUTE_PATHS,
  params?: Record<string, string>
): string => {
  let path: string = ROUTE_PATHS[routeName];

  if (routeName === ROUTES.EDIT_DOCUMENT && params?.slug) {
    // カテゴリとスラグが含まれている場合の処理
    if (params.category) {
      return `/documents/${params.category}/${params.slug}/edit`;
    }
    return `/documents/${params.slug}/edit`;
  } else if (routeName === ROUTES.DOCUMENT_BY_SLUG && params?.slug) {
    return `/documents/${params.slug}`;
  }

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      path = path.replace(`:${key}`, value);
    });
  }
  return path;
};
