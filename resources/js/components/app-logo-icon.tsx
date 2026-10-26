import type { SVGAttributes } from 'react';

const rays = [0, 45, 90, 135, 180, 225, 270, 315];

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="4.75" fill="currentColor" />
            <g
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                fill="none"
            >
                {rays.map((angle) => (
                    <line
                        key={angle}
                        x1="12"
                        y1="1.75"
                        x2="12"
                        y2="4"
                        transform={`rotate(${angle} 12 12)`}
                    />
                ))}
            </g>
        </svg>
    );
}
