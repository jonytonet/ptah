import type { ReactNode } from 'react';

export interface PtahPageHeadProps {
    title: ReactNode;
    /** The line under the title (forge-page-header's `subtitle`). */
    subtitle?: ReactNode;
    /** The trail under the title (forge-breadcrumb): the last one is the current page. */
    crumbs?: { label: ReactNode; url?: string }[];
}

/**
 * The header above a screen: the h1 of forge-page-header (and of the index
 * view ptah:forge writes) with its subtitle, or the breadcrumb "Dashboard / Ticket".
 */
export function PtahPageHead({ title, subtitle, crumbs = [] }: PtahPageHeadProps) {
    return (
        <div className="pl-page-head">
            <h1 className="pl-page-title">{title}</h1>
            {subtitle && <p className="pl-page-subtitle">{subtitle}</p>}
            {crumbs.length > 0 && (
                <nav aria-label="Breadcrumb">
                    <ol className="pl-crumbs">
                        {crumbs.map((crumb, index) => (
                            <li key={index}>
                                {index > 0 && (
                                    <span className="pl-crumb-sep" aria-hidden="true">
                                        /
                                    </span>
                                )}
                                {index < crumbs.length - 1 ? (
                                    <a className="pl-crumb" href={crumb.url ?? '#'}>
                                        {crumb.label}
                                    </a>
                                ) : (
                                    <span className="pl-crumb-current" aria-current="page">
                                        {crumb.label}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ol>
                </nav>
            )}
        </div>
    );
}
