const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Error thrown for every non-2xx response, carrying the HTTP status and the decoded JSON body.
 */
export class HttpError extends Error {
    constructor(status, data) {
        super(data?.message || defaultMessage(status));
        this.status = status;
        this.data = data ?? {};
    }

    /** Laravel validation errors keyed by field, or an empty object. */
    get errors() {
        return this.status === 422 ? (this.data.errors ?? {}) : {};
    }
}

function defaultMessage(status) {
    if (status === 0) return 'Tidak dapat terhubung ke server. Periksa koneksi Anda.';
    if (status === 403) return 'Anda tidak memiliki akses untuk melakukan tindakan ini.';
    if (status === 404) return 'Data tidak ditemukan.';
    if (status === 409) return 'Data sudah diperbarui oleh pengguna lain. Muat ulang data lalu coba lagi.';
    if (status === 419) return 'Sesi Anda telah berakhir. Muat ulang halaman.';
    if (status === 422) return 'Data yang dikirim tidak valid.';

    return 'Terjadi kesalahan pada server. Silakan coba lagi.';
}

/**
 * Build a URL with query parameters, skipping empty values.
 */
export function withQuery(url, params = {}) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.append(key, value);
        }
    });

    const queryString = query.toString();

    return queryString ? `${url}${url.includes('?') ? '&' : '?'}${queryString}` : url;
}

/**
 * Call a JSON endpoint of the application. Plain objects are sent as JSON, FormData as multipart.
 *
 * @throws {HttpError}
 */
export async function http(url, { method = 'GET', body, query, signal } = {}) {
    const isFormData = body instanceof FormData;
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    if (body !== undefined && !isFormData) {
        headers['Content-Type'] = 'application/json';
    }

    let response;

    try {
        response = await fetch(withQuery(url, query), {
            method,
            headers,
            body: body === undefined ? undefined : isFormData ? body : JSON.stringify(body),
            credentials: 'same-origin',
            signal,
        });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new HttpError(0, {});
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new HttpError(response.status, data);
    }

    return data;
}
