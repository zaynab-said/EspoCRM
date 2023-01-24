<?php
return [
  0 => [
    'route' => '/Activities/{scope}/{id}/{name}',
    'method' => 'get',
    'params' => [
      'controller' => 'Activities',
      'action' => 'list',
      'scope' => ':scope',
      'id' => ':id',
      'name' => ':name'
    ]
  ],
  1 => [
    'route' => '/Activities',
    'method' => 'get',
    'params' => [
      'controller' => 'Activities',
      'action' => 'listCalendarEvents'
    ]
  ],
  2 => [
    'route' => '/Timeline',
    'method' => 'get',
    'params' => [
      'controller' => 'Activities',
      'action' => 'getTimeline'
    ]
  ],
  3 => [
    'route' => '/Activities/{scope}/{id}/{name}/list/{entityType}',
    'method' => 'get',
    'params' => [
      'controller' => 'Activities',
      'action' => 'entityTypeList',
      'scope' => ':scope',
      'id' => ':id',
      'name' => ':name',
      'entityType' => ':entityType'
    ]
  ],
  4 => [
    'route' => '/Meeting/{id}/attendees',
    'method' => 'get',
    'params' => [
      'controller' => 'Meeting',
      'action' => 'attendees'
    ]
  ],
  5 => [
    'route' => '/Call/{id}/attendees',
    'method' => 'get',
    'params' => [
      'controller' => 'Call',
      'action' => 'attendees'
    ]
  ],
  6 => [
    'route' => '/',
    'method' => 'get',
    'params' => [
      'controller' => 'ApiIndex',
      'action' => 'index'
    ]
  ],
  7 => [
    'route' => '/App/user',
    'method' => 'get',
    'params' => [
      'controller' => 'App',
      'action' => 'user'
    ]
  ],
  8 => [
    'route' => '/Metadata',
    'method' => 'get',
    'params' => [
      'controller' => 'Metadata'
    ]
  ],
  9 => [
    'route' => '/I18n',
    'method' => 'get',
    'params' => [
      'controller' => 'I18n'
    ],
    'noAuth' => true
  ],
  10 => [
    'route' => '/Settings',
    'method' => 'get',
    'params' => [
      'controller' => 'Settings'
    ],
    'noAuth' => true
  ],
  11 => [
    'route' => '/Settings',
    'method' => 'patch',
    'params' => [
      'controller' => 'Settings'
    ]
  ],
  12 => [
    'route' => '/Settings',
    'method' => 'put',
    'params' => [
      'controller' => 'Settings'
    ]
  ],
  13 => [
    'route' => '/User/passwordChangeRequest',
    'method' => 'post',
    'params' => [
      'controller' => 'User',
      'action' => 'passwordChangeRequest'
    ],
    'noAuth' => true
  ],
  14 => [
    'route' => '/User/changePasswordByRequest',
    'method' => 'post',
    'params' => [
      'controller' => 'User',
      'action' => 'changePasswordByRequest'
    ],
    'noAuth' => true
  ],
  15 => [
    'route' => '/Stream',
    'method' => 'get',
    'params' => [
      'controller' => 'Stream',
      'action' => 'list',
      'scope' => 'User'
    ]
  ],
  16 => [
    'route' => '/GlobalSearch',
    'method' => 'get',
    'params' => [
      'controller' => 'GlobalSearch',
      'action' => 'search'
    ]
  ],
  17 => [
    'route' => '/LeadCapture/{apiKey}',
    'method' => 'post',
    'params' => [
      'controller' => 'LeadCapture',
      'action' => 'leadCapture',
      'apiKey' => ':apiKey'
    ],
    'noAuth' => true
  ],
  18 => [
    'route' => '/LeadCapture/{apiKey}',
    'method' => 'options',
    'params' => [
      'controller' => 'LeadCapture',
      'action' => 'leadCapture',
      'apiKey' => ':apiKey'
    ],
    'noAuth' => true
  ],
  19 => [
    'route' => '/{controller}/action/{action}',
    'method' => 'post',
    'params' => [
      'controller' => ':controller',
      'action' => ':action'
    ]
  ],
  20 => [
    'route' => '/{controller}/action/{action}',
    'method' => 'put',
    'params' => [
      'controller' => ':controller',
      'action' => ':action'
    ]
  ],
  21 => [
    'route' => '/{controller}/action/{action}',
    'method' => 'get',
    'params' => [
      'controller' => ':controller',
      'action' => ':action'
    ]
  ],
  22 => [
    'route' => '/{controller}/layout/{name}',
    'method' => 'get',
    'params' => [
      'controller' => 'Layout',
      'scope' => ':controller'
    ]
  ],
  23 => [
    'route' => '/{controller}/layout/{name}',
    'method' => 'put',
    'params' => [
      'controller' => 'Layout',
      'scope' => ':controller'
    ]
  ],
  24 => [
    'route' => '/{controller}/layout/{name}/{setId}',
    'method' => 'put',
    'params' => [
      'controller' => 'Layout',
      'scope' => ':controller'
    ]
  ],
  25 => [
    'route' => '/Admin/rebuild',
    'method' => 'post',
    'params' => [
      'controller' => 'Admin',
      'action' => 'rebuild'
    ]
  ],
  26 => [
    'route' => '/Admin/clearCache',
    'method' => 'post',
    'params' => [
      'controller' => 'Admin',
      'action' => 'clearCache'
    ]
  ],
  27 => [
    'route' => '/Admin/jobs',
    'method' => 'get',
    'params' => [
      'controller' => 'Admin',
      'action' => 'jobs'
    ]
  ],
  28 => [
    'route' => '/Admin/fieldManager/{scope}/{name}',
    'method' => 'get',
    'params' => [
      'controller' => 'FieldManager',
      'action' => 'read',
      'scope' => ':scope',
      'name' => ':name'
    ]
  ],
  29 => [
    'route' => '/Admin/fieldManager/{scope}',
    'method' => 'post',
    'params' => [
      'controller' => 'FieldManager',
      'action' => 'create',
      'scope' => ':scope'
    ]
  ],
  30 => [
    'route' => '/Admin/fieldManager/{scope}/{name}',
    'method' => 'put',
    'params' => [
      'controller' => 'FieldManager',
      'action' => 'update',
      'scope' => ':scope',
      'name' => ':name'
    ]
  ],
  31 => [
    'route' => '/Admin/fieldManager/{scope}/{name}',
    'method' => 'patch',
    'params' => [
      'controller' => 'FieldManager',
      'action' => 'update',
      'scope' => ':scope',
      'name' => ':name'
    ]
  ],
  32 => [
    'route' => '/Admin/fieldManager/{scope}/{name}',
    'method' => 'delete',
    'params' => [
      'controller' => 'FieldManager',
      'action' => 'delete',
      'scope' => ':scope',
      'name' => ':name'
    ]
  ],
  33 => [
    'route' => '/CurrencyRate',
    'method' => 'put',
    'params' => [
      'controller' => 'CurrencyRate',
      'action' => 'update'
    ]
  ],
  34 => [
    'route' => '/Action',
    'method' => 'post',
    'params' => [
      'controller' => 'Action',
      'action' => 'process'
    ]
  ],
  35 => [
    'route' => '/MassAction',
    'method' => 'post',
    'params' => [
      'controller' => 'MassAction',
      'action' => 'process'
    ]
  ],
  36 => [
    'route' => '/Export',
    'method' => 'post',
    'params' => [
      'controller' => 'Export',
      'action' => 'process'
    ]
  ],
  37 => [
    'route' => '/Kanban/{entityType}',
    'method' => 'get',
    'params' => [
      'controller' => 'Kanban',
      'action' => 'getData'
    ]
  ],
  38 => [
    'route' => '/Attachment/file/{id}',
    'method' => 'get',
    'params' => [
      'controller' => 'Attachment',
      'action' => 'file'
    ]
  ],
  39 => [
    'route' => '/Attachment/chunk/{id}',
    'method' => 'post',
    'params' => [
      'controller' => 'Attachment',
      'action' => 'chunk'
    ]
  ],
  40 => [
    'route' => '/Oidc/authorizationData',
    'method' => 'get',
    'params' => [
      'controller' => 'Oidc',
      'action' => 'authorizationData'
    ],
    'noAuth' => true
  ],
  41 => [
    'route' => '/Oidc/backchannelLogout',
    'method' => 'post',
    'params' => [
      'controller' => 'Oidc',
      'action' => 'backchannelLogout'
    ],
    'noAuth' => true
  ],
  42 => [
    'route' => '/{controller}/{id}',
    'method' => 'get',
    'params' => [
      'controller' => ':controller',
      'action' => 'read',
      'id' => ':id'
    ]
  ],
  43 => [
    'route' => '/{controller}',
    'method' => 'get',
    'params' => [
      'controller' => ':controller',
      'action' => 'index'
    ]
  ],
  44 => [
    'route' => '/{controller}',
    'method' => 'post',
    'params' => [
      'controller' => ':controller',
      'action' => 'create'
    ]
  ],
  45 => [
    'route' => '/{controller}/{id}',
    'method' => 'put',
    'params' => [
      'controller' => ':controller',
      'action' => 'update',
      'id' => ':id'
    ]
  ],
  46 => [
    'route' => '/{controller}/{id}',
    'method' => 'patch',
    'params' => [
      'controller' => ':controller',
      'action' => 'update',
      'id' => ':id'
    ]
  ],
  47 => [
    'route' => '/{controller}/{id}',
    'method' => 'delete',
    'params' => [
      'controller' => ':controller',
      'action' => 'delete',
      'id' => ':id'
    ]
  ],
  48 => [
    'route' => '/{controller}/{id}/stream',
    'method' => 'get',
    'params' => [
      'controller' => 'Stream',
      'action' => 'list',
      'id' => ':id',
      'scope' => ':controller'
    ]
  ],
  49 => [
    'route' => '/{controller}/{id}/posts',
    'method' => 'get',
    'params' => [
      'controller' => 'Stream',
      'action' => 'listPosts',
      'id' => ':id',
      'scope' => ':controller'
    ]
  ],
  50 => [
    'route' => '/{controller}/{id}/subscription',
    'method' => 'put',
    'params' => [
      'controller' => ':controller',
      'id' => ':id',
      'action' => 'follow'
    ]
  ],
  51 => [
    'route' => '/{controller}/{id}/subscription',
    'method' => 'delete',
    'params' => [
      'controller' => ':controller',
      'id' => ':id',
      'action' => 'unfollow'
    ]
  ],
  52 => [
    'route' => '/{controller}/{id}/{link}',
    'method' => 'get',
    'params' => [
      'controller' => ':controller',
      'action' => 'listLinked',
      'id' => ':id',
      'link' => ':link'
    ]
  ],
  53 => [
    'route' => '/{controller}/{id}/{link}',
    'method' => 'post',
    'params' => [
      'controller' => ':controller',
      'action' => 'createLink',
      'id' => ':id',
      'link' => ':link'
    ]
  ],
  54 => [
    'route' => '/{controller}/{id}/{link}',
    'method' => 'delete',
    'params' => [
      'controller' => ':controller',
      'action' => 'removeLink',
      'id' => ':id',
      'link' => ':link'
    ]
  ]
];
