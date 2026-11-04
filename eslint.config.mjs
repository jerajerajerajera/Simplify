// ESLint for the browser scripts in resources/js (checks.yml, job eslint): unused variables, unknown names, duplicate
// keys and unreachable code are errors; `==` and empty blocks are warnings. Run: npx eslint@9 'resources/**/*.js'
const browser = Object.fromEntries(['window', 'document', 'navigator', 'location', 'history', 'localStorage', 'sessionStorage', 'fetch', 'console', 'setTimeout',
  'clearTimeout', 'setInterval', 'clearInterval', 'requestAnimationFrame', 'cancelAnimationFrame', 'URL', 'URLSearchParams', 'matchMedia', 'getComputedStyle',
  'CustomEvent', 'Event', 'MutationObserver', 'ResizeObserver', 'IntersectionObserver', 'Notification', 'Blob', 'FileReader', 'Image', 'HTMLElement',
  'Element', 'Node', 'performance', 'crypto', 'atob', 'btoa', 'AbortController', 'self', 'caches', 'clients', 'registration', 'WebSocket', 'globalThis',
  'queueMicrotask', 'structuredClone', 'Intl', 'DOMParser', 'FormData', 'visualViewport', 'screen', 'devicePixelRatio', 'Alpine', 'Livewire',
  'addEventListener', 'removeEventListener', 'dispatchEvent', 'innerWidth', 'innerHeight', 'scrollX', 'scrollY', 'KeyboardEvent', 'Response', 'Request',
  'Headers', 'TextDecoder', 'TextEncoder', 'open', 'alert', 'confirm',
  'module', 'require', 'process'].map((name) => [name, 'readonly']));

export default [{
  files: ['resources/**/*.js'],
  languageOptions: { ecmaVersion: 2022, sourceType: 'script', globals: browser },
  rules: {
    'no-unused-vars': ['error', { args: 'none', ignoreRestSiblings: true, caughtErrors: 'none' }],
    'no-undef': 'error',
    'no-unreachable': 'error',
    'no-dupe-keys': 'error',
    'no-redeclare': 'error',
    'no-empty': ['warn', { allowEmptyCatch: true }],
    'eqeqeq': ['warn', 'smart'],
  },
}];
