import { iconPaths, iconStroke } from '../core/icons';

export interface IconProps {
    /** A key of `icons` in core/icons.ts. */
    name: string;
    /** Width and height in px (the Blade's w-4 = 16). */
    size?: number;
    strokeWidth?: number;
    className?: string;
}

/** One of ptah's icons: the very SVG of the Livewire screens. Decorative (aria-hidden). */
export function Icon({ name, size = 16, strokeWidth, className }: IconProps) {
    return (
        <svg
            className={className ? `pc-ico ${className}` : 'pc-ico'}
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth ?? iconStroke[name] ?? 2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            {iconPaths(name).map((d) => (
                <path key={d} d={d} />
            ))}
        </svg>
    );
}
