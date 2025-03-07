// ローカル開発用CMS設定
window.CMS_CONFIG = {
    backend: {
      name: "github",
      repo: "eito1011-JP/Handbook",
      branch: "main",
      auth_type: "implicit",
      app_id: "Ov23liAZfY9EcarUr9jm"
    },
    site_url: "http://localhost:3000",
    display_url: "http://localhost:3000",
    publish_mode: "editorial_workflow",
    media_folder: "static/img",
    public_folder: "/img",
    collections: [
      {
        name: "docs",
        label: "Documentation",
        folder: "docs",
        create: true,
        slug: "{{slug}}",
        fields: [
          {label: "ID", name: "id", widget: "string"},
          {label: "Title", name: "title", widget: "string"},
          {label: "Sidebar Label", name: "sidebar_label", widget: "string", required: false},
          {label: "Body", name: "body", widget: "markdown"}
        ]
      }
    ]
  };