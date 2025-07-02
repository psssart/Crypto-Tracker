import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from 'react';

export type Appearance = 'light' | 'dark' | 'system';

interface ThemeContextType {
    appearance: Appearance;
    updateAppearance: (mode: Appearance) => void;
}

const prefersDark = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches;

const applyTheme = (mode: Appearance) => {
    const isDark = mode === 'dark' || (mode === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', isDark);
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [appearance, setAppearance] = useState<Appearance>(() => {
        const stored = (typeof window !== 'undefined' && localStorage.getItem('appearance')) as Appearance | null;
        return stored || 'system';
    });

    const updateAppearance = useCallback((mode: Appearance) => {
        setAppearance(mode);
        localStorage.setItem('appearance', mode);
        applyTheme(mode);
    }, []);

    useEffect(() => {
        applyTheme(appearance);
        const mql = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => applyTheme(localStorage.getItem('appearance') as Appearance || 'system');
        mql.addEventListener('change', onChange);
        return () => mql.removeEventListener('change', onChange);
    }, [appearance]);

    return (
        <ThemeContext.Provider value={{ appearance, updateAppearance }}>
    {children}
    </ThemeContext.Provider>
);
}

export function useTheme() {
    const ctx = useContext(ThemeContext);
    if (!ctx) throw new Error('useTheme must be inside ThemeProvider');
    return ctx;
}

// Call this **once**, before React hydrates
export function initializeTheme() {
    const stored = (localStorage.getItem('appearance') as Appearance) || 'system';
    applyTheme(stored);
}
