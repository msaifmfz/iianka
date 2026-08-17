import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    alt = '',
    ...props
}: Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'>) {
    return <img src="/icon-192.png" alt={alt} {...props} />;
}
