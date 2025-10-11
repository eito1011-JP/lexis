import type { AspidaClient } from 'aspida';
import { dataToURLString } from 'aspida';
import type { Methods as Methods_stzzet } from './activity-logs';
import type { Methods as Methods_85qyf7 } from './auth/logout';
import type { Methods as Methods_109kc9r } from './auth/me';
import type { Methods as Methods_12vt0nz } from './auth/pre-users';
import type { Methods as Methods_1xs246r } from './auth/signin-with-email';
import type { Methods as Methods_10ygvz6 } from './category-entities';
import type { Methods as Methods_1hz00if } from './category-entities/_entityId@number';
import type { Methods as Methods_m7eodo } from './commits';
import type { Methods as Methods_n9z2k1 } from './document-entities';
import type { Methods as Methods_68m4j2 } from './document-entities/_entityId@number';
import type { Methods as Methods_11eupjd } from './fix-requests/_token';
import type { Methods as Methods_yifr8d } from './fix-requests/apply';
import type { Methods as Methods_ymax8f } from './nodes';
import type { Methods as Methods_yyj2kq } from './organizations';
import type { Methods as Methods_1a7bieq } from './pull-request-edit-sessions';
import type { Methods as Methods_1b21cg6 } from './pull-request-edit-sessions/detail';
import type { Methods as Methods_qivt1s } from './pull-request-reviewers';
import type { Methods as Methods_1low71x } from './pull-request-reviewers/_userId@number/resend';
import type { Methods as Methods_1tql4u2 } from './pull-requests';
import type { Methods as Methods_6qcpcm } from './pull-requests/_id@number';
import type { Methods as Methods_1r8wpr7 } from './pull-requests/_id@number/activity-log-on-pull-request';
import type { Methods as Methods_1p42mtc } from './pull-requests/_id@number/approve';
import type { Methods as Methods_1cgboht } from './pull-requests/_id@number/close';
import type { Methods as Methods_1yinjm9 } from './pull-requests/_id@number/conflict/diff';
import type { Methods as Methods_1o4qvb5 } from './pull-requests/_id@number/conflict/temporary';
import type { Methods as Methods_1ndlm46 } from './pull-requests/_id@number/fix-request';
import type { Methods as Methods_1czorqh } from './pull-requests/_id@number/merge';
import type { Methods as Methods_11i5tqy } from './pull-requests/_id@number/update';
import type { Methods as Methods_113936e } from './user-branch-sessions';
import type { Methods as Methods_vxa6jd } from './user-branches/_userBranchId@number';
import type { Methods as Methods_10ka6um } from './user-branches/diff';
import type { Methods as Methods_jzr18p } from './users/me';

const api = <T>({ baseURL, fetch }: AspidaClient<T>) => {
  const prefix = (baseURL === undefined ? '/api' : baseURL).replace(/\/$/, '');
  const PATH0 = '/activity-logs';
  const PATH1 = '/auth/logout';
  const PATH2 = '/auth/me';
  const PATH3 = '/auth/pre-users';
  const PATH4 = '/auth/signin-with-email';
  const PATH5 = '/category-entities';
  const PATH6 = '/commits';
  const PATH7 = '/document-entities';
  const PATH8 = '/fix-requests';
  const PATH9 = '/fix-requests/apply';
  const PATH10 = '/nodes';
  const PATH11 = '/organizations';
  const PATH12 = '/pull-request-edit-sessions';
  const PATH13 = '/pull-request-edit-sessions/detail';
  const PATH14 = '/pull-request-reviewers';
  const PATH15 = '/resend';
  const PATH16 = '/pull-requests';
  const PATH17 = '/activity-log-on-pull-request';
  const PATH18 = '/approve';
  const PATH19 = '/close';
  const PATH20 = '/conflict/diff';
  const PATH21 = '/conflict/temporary';
  const PATH22 = '/fix-request';
  const PATH23 = '/merge';
  const PATH24 = '/update';
  const PATH25 = '/user-branch-sessions';
  const PATH26 = '/user-branches';
  const PATH27 = '/user-branches/diff';
  const PATH28 = '/users/me';
  const GET = 'GET';
  const POST = 'POST';
  const PUT = 'PUT';
  const DELETE = 'DELETE';
  const PATCH = 'PATCH';

  return {
    /**
     * POST /api/activity-logs
     */
    activity_logs: {
      post: (option: { body: Methods_stzzet['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_stzzet['post']['resBody']>(prefix, PATH0, POST, option).json(),
      $post: (option: { body: Methods_stzzet['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_stzzet['post']['resBody']>(prefix, PATH0, POST, option).json().then(r => r.body),
      $path: () => `${prefix}${PATH0}`,
    },
    auth: {
      /**
       * POST /api/auth/logout
       */
      logout: {
        post: (option: { body: Methods_85qyf7['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_85qyf7['post']['resBody']>(prefix, PATH1, POST, option).json(),
        $post: (option: { body: Methods_85qyf7['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_85qyf7['post']['resBody']>(prefix, PATH1, POST, option).json().then(r => r.body),
        $path: () => `${prefix}${PATH1}`,
      },
      /**
       * GET /api/auth/me
       */
      me: {
        get: (option?: { config?: T | undefined } | undefined) =>
          fetch<Methods_109kc9r['get']['resBody']>(prefix, PATH2, GET, option).json(),
        $get: (option?: { config?: T | undefined } | undefined) =>
          fetch<Methods_109kc9r['get']['resBody']>(prefix, PATH2, GET, option).json().then(r => r.body),
        $path: () => `${prefix}${PATH2}`,
      },
      /**
       * POST /api/auth/pre-users
       */
      pre_users: {
        post: (option: { body: Methods_12vt0nz['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_12vt0nz['post']['resBody']>(prefix, PATH3, POST, option).json(),
        $post: (option: { body: Methods_12vt0nz['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_12vt0nz['post']['resBody']>(prefix, PATH3, POST, option).json().then(r => r.body),
        $path: () => `${prefix}${PATH3}`,
      },
      /**
       * POST /api/auth/signin-with-email
       */
      signin_with_email: {
        post: (option: { body: Methods_1xs246r['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_1xs246r['post']['resBody']>(prefix, PATH4, POST, option).json(),
        $post: (option: { body: Methods_1xs246r['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_1xs246r['post']['resBody']>(prefix, PATH4, POST, option).json().then(r => r.body),
        $path: () => `${prefix}${PATH4}`,
      },
    },
    /**
     * GET /api/category-entities
     * POST /api/category-entities
     */
    category_entities: {
      /**
       * GET /api/category-entities/:entityId
       * PUT /api/category-entities/:entityId
       * DELETE /api/category-entities/:entityId
       */
      _entityId: (val1: number) => {
        const prefix1 = `${PATH5}/${val1}`;

        return {
          get: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_1hz00if['get']['resBody']>(prefix, prefix1, GET, option).json(),
          $get: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_1hz00if['get']['resBody']>(prefix, prefix1, GET, option).json().then(r => r.body),
          put: (option: { body: Methods_1hz00if['put']['reqBody'], config?: T | undefined }) =>
            fetch<Methods_1hz00if['put']['resBody']>(prefix, prefix1, PUT, option).json(),
          $put: (option: { body: Methods_1hz00if['put']['reqBody'], config?: T | undefined }) =>
            fetch<Methods_1hz00if['put']['resBody']>(prefix, prefix1, PUT, option).json().then(r => r.body),
          delete: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_1hz00if['delete']['resBody']>(prefix, prefix1, DELETE, option).json(),
          $delete: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_1hz00if['delete']['resBody']>(prefix, prefix1, DELETE, option).json().then(r => r.body),
          $path: () => `${prefix}${prefix1}`,
        };
      },
      get: (option?: { query?: Methods_10ygvz6['get']['query'] | undefined, config?: T | undefined } | undefined) =>
        fetch<Methods_10ygvz6['get']['resBody']>(prefix, PATH5, GET, option).json(),
      $get: (option?: { query?: Methods_10ygvz6['get']['query'] | undefined, config?: T | undefined } | undefined) =>
        fetch<Methods_10ygvz6['get']['resBody']>(prefix, PATH5, GET, option).json().then(r => r.body),
      post: (option: { body: Methods_10ygvz6['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_10ygvz6['post']['resBody']>(prefix, PATH5, POST, option).json(),
      $post: (option: { body: Methods_10ygvz6['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_10ygvz6['post']['resBody']>(prefix, PATH5, POST, option).json().then(r => r.body),
      $path: (option?: { method?: 'get' | undefined; query: Methods_10ygvz6['get']['query'] } | undefined) =>
        `${prefix}${PATH5}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
    },
    commits: {
      post: (option: { body: Methods_m7eodo['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_m7eodo['post']['resBody']>(prefix, PATH6, POST, option).json(),
      $post: (option: { body: Methods_m7eodo['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_m7eodo['post']['resBody']>(prefix, PATH6, POST, option).json().then(r => r.body),
      $path: () => `${prefix}${PATH6}`,
    },
    /**
     * POST /api/document-entities
     */
    document_entities: {
      /**
       * GET /api/document-entities/:entityId
       * PUT /api/document-entities/:entityId
       * DELETE /api/document-entities/:entityId
       */
      _entityId: (val1: number) => {
        const prefix1 = `${PATH7}/${val1}`;

        return {
          get: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_68m4j2['get']['resBody']>(prefix, prefix1, GET, option).json(),
          $get: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_68m4j2['get']['resBody']>(prefix, prefix1, GET, option).json().then(r => r.body),
          put: (option: { body: Methods_68m4j2['put']['reqBody'], config?: T | undefined }) =>
            fetch<Methods_68m4j2['put']['resBody']>(prefix, prefix1, PUT, option).json(),
          $put: (option: { body: Methods_68m4j2['put']['reqBody'], config?: T | undefined }) =>
            fetch<Methods_68m4j2['put']['resBody']>(prefix, prefix1, PUT, option).json().then(r => r.body),
          delete: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_68m4j2['delete']['resBody']>(prefix, prefix1, DELETE, option).json(),
          $delete: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_68m4j2['delete']['resBody']>(prefix, prefix1, DELETE, option).json().then(r => r.body),
          $path: () => `${prefix}${prefix1}`,
        };
      },
      post: (option: { body: Methods_n9z2k1['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_n9z2k1['post']['resBody']>(prefix, PATH7, POST, option).json(),
      $post: (option: { body: Methods_n9z2k1['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_n9z2k1['post']['resBody']>(prefix, PATH7, POST, option).json().then(r => r.body),
      $path: () => `${prefix}${PATH7}`,
    },
    fix_requests: {
      /**
       * GET /api/fix-requests/:token
       */
      _token: (val1: number | string) => {
        const prefix1 = `${PATH8}/${val1}`;

        return {
          get: (option?: { query?: Methods_11eupjd['get']['query'] | undefined, config?: T | undefined } | undefined) =>
            fetch<Methods_11eupjd['get']['resBody']>(prefix, prefix1, GET, option).json(),
          $get: (option?: { query?: Methods_11eupjd['get']['query'] | undefined, config?: T | undefined } | undefined) =>
            fetch<Methods_11eupjd['get']['resBody']>(prefix, prefix1, GET, option).json().then(r => r.body),
          $path: (option?: { method?: 'get' | undefined; query: Methods_11eupjd['get']['query'] } | undefined) =>
            `${prefix}${prefix1}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
        };
      },
      /**
       * POST /api/fix-requests/apply
       */
      apply: {
        post: (option: { body: Methods_yifr8d['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_yifr8d['post']['resBody']>(prefix, PATH9, POST, option).json(),
        $post: (option: { body: Methods_yifr8d['post']['reqBody'], config?: T | undefined }) =>
          fetch<Methods_yifr8d['post']['resBody']>(prefix, PATH9, POST, option).json().then(r => r.body),
        $path: () => `${prefix}${PATH9}`,
      },
    },
    /**
     * GET /api/nodes
     */
    nodes: {
      get: (option: { query: Methods_ymax8f['get']['query'], config?: T | undefined }) =>
        fetch<Methods_ymax8f['get']['resBody']>(prefix, PATH10, GET, option).json(),
      $get: (option: { query: Methods_ymax8f['get']['query'], config?: T | undefined }) =>
        fetch<Methods_ymax8f['get']['resBody']>(prefix, PATH10, GET, option).json().then(r => r.body),
      $path: (option?: { method?: 'get' | undefined; query: Methods_ymax8f['get']['query'] } | undefined) =>
        `${prefix}${PATH10}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
    },
    /**
     * POST /api/organizations
     */
    organizations: {
      post: (option: { body: Methods_yyj2kq['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_yyj2kq['post']['resBody']>(prefix, PATH11, POST, option).json(),
      $post: (option: { body: Methods_yyj2kq['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_yyj2kq['post']['resBody']>(prefix, PATH11, POST, option).json().then(r => r.body),
      $path: () => `${prefix}${PATH11}`,
    },
    /**
     * POST /api/pull-request-edit-sessions (編集セッション開始)
     * PATCH /api/pull-request-edit-sessions (編集セッション終了)
     */
    pull_request_edit_sessions: {
      /**
       * GET /api/pull-request-edit-sessions/detail
       */
      detail: {
        get: (option: { query: Methods_1b21cg6['get']['query'], config?: T | undefined }) =>
          fetch<Methods_1b21cg6['get']['resBody']>(prefix, PATH13, GET, option).json(),
        $get: (option: { query: Methods_1b21cg6['get']['query'], config?: T | undefined }) =>
          fetch<Methods_1b21cg6['get']['resBody']>(prefix, PATH13, GET, option).json().then(r => r.body),
        $path: (option?: { method?: 'get' | undefined; query: Methods_1b21cg6['get']['query'] } | undefined) =>
          `${prefix}${PATH13}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
      },
      post: (option: { body: Methods_1a7bieq['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_1a7bieq['post']['resBody']>(prefix, PATH12, POST, option).json(),
      $post: (option: { body: Methods_1a7bieq['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_1a7bieq['post']['resBody']>(prefix, PATH12, POST, option).json().then(r => r.body),
      patch: (option: { body: Methods_1a7bieq['patch']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_1a7bieq['patch']['resBody']>(prefix, PATH12, PATCH, option).json(),
      $patch: (option: { body: Methods_1a7bieq['patch']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_1a7bieq['patch']['resBody']>(prefix, PATH12, PATCH, option).json().then(r => r.body),
      $path: () => `${prefix}${PATH12}`,
    },
    pull_request_reviewers: {
      _userId: (val1: number) => {
        const prefix1 = `${PATH14}/${val1}`;

        return {
          /**
           * PATCH /api/pull-request-reviewers/:userId/resend
           */
          resend: {
            patch: (option: { body: Methods_1low71x['patch']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_1low71x['patch']['resBody']>(prefix, `${prefix1}${PATH15}`, PATCH, option).json(),
            $patch: (option: { body: Methods_1low71x['patch']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_1low71x['patch']['resBody']>(prefix, `${prefix1}${PATH15}`, PATCH, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH15}`,
          },
        };
      },
      get: (option?: { query?: Methods_qivt1s['get']['query'] | undefined, config?: T | undefined } | undefined) =>
        fetch<Methods_qivt1s['get']['resBody']>(prefix, PATH14, GET, option).json(),
      $get: (option?: { query?: Methods_qivt1s['get']['query'] | undefined, config?: T | undefined } | undefined) =>
        fetch<Methods_qivt1s['get']['resBody']>(prefix, PATH14, GET, option).json().then(r => r.body),
      post: (option: { body: Methods_qivt1s['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_qivt1s['post']['resBody']>(prefix, PATH14, POST, option).json(),
      $post: (option: { body: Methods_qivt1s['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_qivt1s['post']['resBody']>(prefix, PATH14, POST, option).json().then(r => r.body),
      $path: (option?: { method?: 'get' | undefined; query: Methods_qivt1s['get']['query'] } | undefined) =>
        `${prefix}${PATH14}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
    },
    /**
     * GET /api/pull-requests
     * POST /api/pull-requests
     */
    pull_requests: {
      /**
       * GET /api/pull-requests/:id
       */
      _id: (val1: number) => {
        const prefix1 = `${PATH16}/${val1}`;

        return {
          /**
           * GET /api/pull-requests/:id/activity-log-on-pull-request
           */
          activity_log_on_pull_request: {
            get: (option?: { config?: T | undefined } | undefined) =>
              fetch<Methods_1r8wpr7['get']['resBody']>(prefix, `${prefix1}${PATH17}`, GET, option).json(),
            $get: (option?: { config?: T | undefined } | undefined) =>
              fetch<Methods_1r8wpr7['get']['resBody']>(prefix, `${prefix1}${PATH17}`, GET, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH17}`,
          },
          /**
           * PATCH /api/pull-requests/:id/approve
           */
          approve: {
            patch: (option?: { config?: T | undefined } | undefined) =>
              fetch<Methods_1p42mtc['patch']['resBody']>(prefix, `${prefix1}${PATH18}`, PATCH, option).json(),
            $patch: (option?: { config?: T | undefined } | undefined) =>
              fetch<Methods_1p42mtc['patch']['resBody']>(prefix, `${prefix1}${PATH18}`, PATCH, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH18}`,
          },
          /**
           * PATCH /api/pull-requests/:id/close
           */
          close: {
            patch: (option?: { config?: T | undefined } | undefined) =>
              fetch<Methods_1cgboht['patch']['resBody']>(prefix, `${prefix1}${PATH19}`, PATCH, option).json(),
            $patch: (option?: { config?: T | undefined } | undefined) =>
              fetch<Methods_1cgboht['patch']['resBody']>(prefix, `${prefix1}${PATH19}`, PATCH, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH19}`,
          },
          conflict: {
            /**
             * GET /api/pull-requests/:id/conflict/diff
             */
            diff: {
              get: (option?: { config?: T | undefined } | undefined) =>
                fetch<Methods_1yinjm9['get']['resBody']>(prefix, `${prefix1}${PATH20}`, GET, option).json(),
              $get: (option?: { config?: T | undefined } | undefined) =>
                fetch<Methods_1yinjm9['get']['resBody']>(prefix, `${prefix1}${PATH20}`, GET, option).json().then(r => r.body),
              $path: () => `${prefix}${prefix1}${PATH20}`,
            },
            /**
             * POST /api/pull-requests/:id/conflict/temporary
             */
            temporary: {
              post: (option: { body: Methods_1o4qvb5['post']['reqBody'], config?: T | undefined }) =>
                fetch<Methods_1o4qvb5['post']['resBody']>(prefix, `${prefix1}${PATH21}`, POST, option).json(),
              $post: (option: { body: Methods_1o4qvb5['post']['reqBody'], config?: T | undefined }) =>
                fetch<Methods_1o4qvb5['post']['resBody']>(prefix, `${prefix1}${PATH21}`, POST, option).json().then(r => r.body),
              $path: () => `${prefix}${prefix1}${PATH21}`,
            },
          },
          /**
           * POST /api/pull-requests/:id/fix-request
           */
          fix_request: {
            post: (option: { body: Methods_1ndlm46['post']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_1ndlm46['post']['resBody']>(prefix, `${prefix1}${PATH22}`, POST, option).json(),
            $post: (option: { body: Methods_1ndlm46['post']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_1ndlm46['post']['resBody']>(prefix, `${prefix1}${PATH22}`, POST, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH22}`,
          },
          /**
           * PUT /api/pull-requests/:id/merge
           */
          merge: {
            put: (option: { body: Methods_1czorqh['put']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_1czorqh['put']['resBody']>(prefix, `${prefix1}${PATH23}`, PUT, option).json(),
            $put: (option: { body: Methods_1czorqh['put']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_1czorqh['put']['resBody']>(prefix, `${prefix1}${PATH23}`, PUT, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH23}`,
          },
          /**
           * PATCH /api/pull-requests/:id/update
           */
          update: {
            patch: (option: { body: Methods_11i5tqy['patch']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_11i5tqy['patch']['resBody']>(prefix, `${prefix1}${PATH24}`, PATCH, option).json(),
            $patch: (option: { body: Methods_11i5tqy['patch']['reqBody'], config?: T | undefined }) =>
              fetch<Methods_11i5tqy['patch']['resBody']>(prefix, `${prefix1}${PATH24}`, PATCH, option).json().then(r => r.body),
            $path: () => `${prefix}${prefix1}${PATH24}`,
          },
          get: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_6qcpcm['get']['resBody']>(prefix, prefix1, GET, option).json(),
          $get: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_6qcpcm['get']['resBody']>(prefix, prefix1, GET, option).json().then(r => r.body),
          $path: () => `${prefix}${prefix1}`,
        };
      },
      get: (option?: { query?: Methods_1tql4u2['get']['query'] | undefined, config?: T | undefined } | undefined) =>
        fetch<Methods_1tql4u2['get']['resBody']>(prefix, PATH16, GET, option).json(),
      $get: (option?: { query?: Methods_1tql4u2['get']['query'] | undefined, config?: T | undefined } | undefined) =>
        fetch<Methods_1tql4u2['get']['resBody']>(prefix, PATH16, GET, option).json().then(r => r.body),
      post: (option: { body: Methods_1tql4u2['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_1tql4u2['post']['resBody']>(prefix, PATH16, POST, option).json(),
      $post: (option: { body: Methods_1tql4u2['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_1tql4u2['post']['resBody']>(prefix, PATH16, POST, option).json().then(r => r.body),
      $path: (option?: { method?: 'get' | undefined; query: Methods_1tql4u2['get']['query'] } | undefined) =>
        `${prefix}${PATH16}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
    },
    user_branch_sessions: {
      post: (option: { body: Methods_113936e['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_113936e['post']['resBody']>(prefix, PATH25, POST, option).json(),
      $post: (option: { body: Methods_113936e['post']['reqBody'], config?: T | undefined }) =>
        fetch<Methods_113936e['post']['resBody']>(prefix, PATH25, POST, option).json().then(r => r.body),
      $path: () => `${prefix}${PATH25}`,
    },
    user_branches: {
      /**
       * DELETE /api/user-branches/:userBranchId
       */
      _userBranchId: (val1: number) => {
        const prefix1 = `${PATH26}/${val1}`;

        return {
          delete: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_vxa6jd['delete']['resBody']>(prefix, prefix1, DELETE, option).json(),
          $delete: (option?: { config?: T | undefined } | undefined) =>
            fetch<Methods_vxa6jd['delete']['resBody']>(prefix, prefix1, DELETE, option).json().then(r => r.body),
          $path: () => `${prefix}${prefix1}`,
        };
      },
      /**
       * GET /api/user-branches/diff
       */
      diff: {
        get: (option: { query: Methods_10ka6um['get']['query'], config?: T | undefined }) =>
          fetch<Methods_10ka6um['get']['resBody']>(prefix, PATH27, GET, option).json(),
        $get: (option: { query: Methods_10ka6um['get']['query'], config?: T | undefined }) =>
          fetch<Methods_10ka6um['get']['resBody']>(prefix, PATH27, GET, option).json().then(r => r.body),
        $path: (option?: { method?: 'get' | undefined; query: Methods_10ka6um['get']['query'] } | undefined) =>
          `${prefix}${PATH27}${option && option.query ? `?${dataToURLString(option.query)}` : ''}`,
      },
    },
    users: {
      me: {
        get: (option?: { config?: T | undefined } | undefined) =>
          fetch<Methods_jzr18p['get']['resBody']>(prefix, PATH28, GET, option).json(),
        $get: (option?: { config?: T | undefined } | undefined) =>
          fetch<Methods_jzr18p['get']['resBody']>(prefix, PATH28, GET, option).json().then(r => r.body),
        $path: () => `${prefix}${PATH28}`,
      },
    },
  };
};

export type ApiInstance = ReturnType<typeof api>;
export default api;
