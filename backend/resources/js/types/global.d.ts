/// <reference types="vite/client" />

declare module '*.svg' {
    const content: string;
    export default content;
}

declare module '*.png' {
    const content: string;
    export default content;
}

// Ziggy route helper
declare function route(name: string, params?: Record<string, string | number>, absolute?: boolean): string;
declare function route(): { current: (name: string) => boolean };
