export const ROUTES = {
  HOME: 'home',
  LOGIN: 'login',
  SIGNUP: 'signup',
  DOCUMENTS: 'documents',
  CREATE_DOCUMENT: 'create-document',
  EDIT_DOCUMENT: 'edit-document',
  DOCUMENT_BY_SLUG: 'document-by-slug',
  CHANGE_SUGGESTIONS: 'change-suggestions',
} as const;

export const ROUTE_PATHS = {
  [ROUTES.HOME]: '/',
  [ROUTES.LOGIN]: '/login',
  [ROUTES.SIGNUP]: '/signup',
  [ROUTES.DOCUMENTS]: '/documents',
  [ROUTES.CREATE_DOCUMENT]: '/documents/create',
  [ROUTES.EDIT_DOCUMENT]: '/documents/**/edit',
  [ROUTES.DOCUMENT_BY_SLUG]: '/documents/:paths/*',
  [ROUTES.CHANGE_SUGGESTIONS]: '/change-suggestions',
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
