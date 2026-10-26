import { Head, Link, usePage } from '@inertiajs/react';
import { CloudRain, MapPin, Thermometer } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { login, register, weather } from '@/routes';

const features = [
    {
        icon: Thermometer,
        title: 'Heure par heure',
        description:
            'Température, ressenti, humidité et point de rosée sur 4 jours.',
    },
    {
        icon: CloudRain,
        title: 'Pluie et neige',
        description: 'Cumuls de précipitations par heure et par jour.',
    },
    {
        icon: MapPin,
        title: 'Vos villes',
        description:
            'Enregistrez vos localisations pour les retrouver en un clic.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Bienvenue" />
            <div className="sky-backdrop flex min-h-screen flex-col items-center px-6 py-6 lg:py-8">
                <header className="flex w-full max-w-4xl items-center justify-between">
                    <span className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-7 text-amber-500" />
                        {name}
                    </span>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={weather()}>Voir la météo</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={login()}>Se connecter</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Créer un compte
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="flex w-full max-w-4xl flex-1 flex-col items-center justify-center gap-12 py-12 text-center">
                    <div className="flex flex-col items-center gap-6">
                        <AppLogoIcon className="sun-spin size-24 text-amber-500 drop-shadow-[0_0_24px_rgb(251_191_36/0.6)]" />
                        <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            La météo de vos villes,
                            <br />
                            <span className="text-primary">
                                heure par heure
                            </span>
                        </h1>
                        <p className="max-w-xl text-lg text-muted-foreground">
                            Prévisions détaillées issues du modèle Météo-France,
                            pour toutes les villes que vous suivez.
                        </p>
                        {!auth.user && (
                            <Button asChild size="lg">
                                <Link href={register()}>Commencer</Link>
                            </Button>
                        )}
                    </div>

                    <ul className="grid w-full gap-4 sm:grid-cols-3">
                        {features.map((feature) => (
                            <li
                                key={feature.title}
                                className="weather-card rounded-xl border bg-card/80 p-5 text-left backdrop-blur"
                            >
                                <feature.icon className="mb-3 size-6 text-primary" />
                                <h2 className="font-medium">{feature.title}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {feature.description}
                                </p>
                            </li>
                        ))}
                    </ul>
                </main>

                <footer className="text-xs text-muted-foreground">
                    Données météo : Open-Meteo · modèle Météo-France
                </footer>
            </div>
        </>
    );
}
