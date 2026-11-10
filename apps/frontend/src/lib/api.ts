export interface User { id: string; name: string; roles: string[]; permissions: string[] }
export type Row = Record<string, unknown>
export interface Envelope<T> { data: T; meta?: { total?: number; current_page?: number; last_page?: number; per_page?: number } }

export class ApiError extends Error {
  constructor(message: string, public status: number, public fields: Record<string, string[]> = {}, public requestId?: string) { super(message) }
}

export function createApi(getToken: () => string | null, expired: () => void) {
  return async function request<T>(path: string, options: { method?: string; body?: unknown; signal?: AbortSignal; download?: boolean } = {}): Promise<T> {
    const headers = new Headers({ Accept: 'application/json' })
    const token = getToken()
    if (token) headers.set('Authorization', `Bearer ${token}`)
    const multipart = options.body instanceof FormData
    if (options.body !== undefined && !multipart) headers.set('Content-Type', 'application/json')
    let response: Response
    try {
      response = await fetch(`/api/v1${path}`, { method: options.method ?? 'GET', headers,
        body: options.body === undefined ? undefined : multipart ? options.body as FormData : JSON.stringify(options.body), signal: options.signal })
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') throw error
      throw new ApiError('تعذر الاتصال بالخدمة. تحقق من تشغيل الخادم ثم أعد المحاولة.', 0)
    }
    if (response.ok && options.download) return await response.blob() as T
    const body = response.status === 204 ? {} : await response.json().catch(() => ({}))
    if (!response.ok) {
      if (response.status === 401 && token) expired()
      const fallback: Record<number, string> = { 401: 'انتهت الجلسة. سجل الدخول مجددًا.', 403: 'ليس لديك تصريح لهذا الإجراء.', 409: 'تغير السجل. حدّث البيانات قبل المحاولة.', 422: 'راجع الحقول المحددة.', 503: 'الخدمة غير متاحة حاليًا.' }
      throw new ApiError(body.error?.message ?? body.message ?? fallback[response.status] ?? 'تعذر إتمام الطلب.', response.status,
        body.errors ?? body.error?.details ?? {}, body.error?.request_id ?? response.headers.get('X-Request-ID') ?? undefined)
    }
    return body as T
  }
}

export type Api = ReturnType<typeof createApi>
export function rowsOf(value: unknown): Row[] {
  if (Array.isArray(value)) return value
  if (value && typeof value === 'object' && 'data' in value) return rowsOf(value.data)
  return []
}
