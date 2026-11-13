/**
 * Click-to-chat helpers. The phone is normalized on the server (App\Support\Whatsapp),
 * so `number` here is already international digits (e.g. 573001234567) or null.
 */

export interface MessageContext {
    name: string | null;
    plate: string;
    business: string;
}

const greeting = (name: string | null) => (name?.trim() ? `Hola ${name.trim()},` : 'Hola,');

export const messageTemplates = (context: MessageContext) => [
    {
        key: 'saludo',
        label: 'Saludo',
        text: `${greeting(context.name)} te escribimos de ${context.business} por tu vehículo con placa ${context.plate}.`,
    },
    {
        key: 'recordatorio',
        label: 'Recordatorio',
        text: `${greeting(context.name)} ya pasó un tiempo desde la última lavada de tu vehículo con placa ${context.plate}. ¡Te esperamos en ${context.business}!`,
    },
];

export const whatsappLink = (number: string | null, message: string): string | null => {
    const text = message.trim();
    return number && text ? `https://wa.me/${number}?text=${encodeURIComponent(text)}` : null;
};
