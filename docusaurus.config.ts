import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';
import type {PluginConfig} from '@docusaurus/types';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

const config: Config = {
  title: 'My Site',
  tagline: 'Dinosaurs are cool',
  favicon: 'img/favicon.ico',

  // Set the production url of your site here
  url: 'https://eito1011-JP.github.io', // あなたのGitHubユーザー名/組織名に変更
  // Set the /<baseUrl>/ pathname under which your site is served
  // For GitHub pages deployment, it is often '/<projectName>/'
  baseUrl: '/Handbook/', // リポジトリ名に変更

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: 'eito1011-JP', // あなたのGitHubユーザー名/組織名
  projectName: 'Handbook', // リポジトリ名
  trailingSlash: false, // GitHub Pagesの場合はfalseに設定

  onBrokenLinks: 'warn',
  onBrokenMarkdownLinks: 'warn',

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },
  scripts: [
    {
      src: 'https://identity.netlify.com/v1/netlify-identity-widget.js',
      async: true,
    },
  ],

  // plugins: [
  //   [
  //     '@docusaurus/plugin-client-redirects',
  //     {
  //       redirects: [
  //         {
  //           from: '/admin',
  //           to: '/admin/',
  //         },
  //       ],
  //     },
  //   ],
  // ] as PluginConfig[],

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl:
            'https://github.com/eito1011-JP/Handbook/tree/main/', // あなたのリポジトリURLに変更
          // 追加: 自動サイドバー設定
          routeBasePath: 'docs',
          // ファイル構造に基づいてサイドバーを自動生成
          sidebarItemsGenerator: async function({
            defaultSidebarItemsGenerator,
            ...args
          }) {
            const sidebarItems = await defaultSidebarItemsGenerator(args);
            return sidebarItems;
          },
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    // 残りの設定は変更なし
    // ...
  } satisfies Preset.ThemeConfig,
};

export default config;