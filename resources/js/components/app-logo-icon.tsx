import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGSVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <circle
                cx="24"
                cy="9"
                r="4.5"
                stroke="currentColor"
                strokeWidth="3"
            />
            <path
                d="M24 13.5V33"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="3"
            />
            <path
                d="M15.5 21.5H32.5"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="3"
            />
            <path
                d="M11.5 29.5C14.25 34.8333 18.4167 37.5 24 37.5C29.5833 37.5 33.75 34.8333 36.5 29.5"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="3"
            />
            <path
                d="M14 28.5L11.5 38.5"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="3"
            />
            <path
                d="M34 28.5L36.5 38.5"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="3"
            />
            <path
                d="M20 33.5C20.8333 35.8333 22.1667 37 24 37C25.8333 37 27.1667 35.8333 28 33.5"
                stroke="currentColor"
                strokeLinecap="round"
                strokeWidth="3"
            />
        </svg>
    );
}
