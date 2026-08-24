import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { RegisterPage } from './RegisterPage';

const mockFetch = vi.fn<typeof fetch>();

const renderPage = () =>
  render(
    <MemoryRouter>
      <RegisterPage />
    </MemoryRouter>,
  );

const captchaResponse = () =>
  new Response(JSON.stringify({ index: 3, question: 'What is 2 + 2?' }), { status: 200 });

describe('RegisterPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('fetches and displays captcha question on mount', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() =>
      expect(screen.getByText('What is 2 + 2?')).toBeInTheDocument(),
    );
  });

  it('shows success message on 201', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() => screen.getByText('What is 2 + 2?'));

    // Fill form
    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');
    await userEvent.type(screen.getByLabelText(/captcha/i), '4');

    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ message: 'Registration submitted. Awaiting admin approval.' }),
        { status: 201 },
      ),
    );

    await userEvent.click(screen.getByRole('button', { name: /register/i }));

    await waitFor(() =>
      expect(screen.getByText(/awaiting admin approval/i)).toBeInTheDocument(),
    );
  });

  it('submits correct payload including captcha_index', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() => screen.getByText('What is 2 + 2?'));

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');
    await userEvent.type(screen.getByLabelText(/captcha/i), '4');

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Registration submitted. Awaiting admin approval.' }), { status: 201 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /register/i }));

    await waitFor(() => expect(mockFetch).toHaveBeenCalledTimes(2));

    const [, registerCall] = mockFetch.mock.calls;
    const body = JSON.parse((registerCall as [string, RequestInit])[1].body as string) as Record<string, unknown>;
    expect(body.captcha_index).toBe(3);
    expect(body.captcha_answer).toBe('4');
  });

  it('shows registration-closed banner on 423', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() => screen.getByText('What is 2 + 2?'));

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');
    await userEvent.type(screen.getByLabelText(/captcha/i), '4');

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Registration closed' }), { status: 423 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /register/i }));

    await waitFor(() =>
      expect(screen.getByText(/registration is closed/i)).toBeInTheDocument(),
    );
  });

  it('shows error when captcha fetch fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network'));
    renderPage();
    await waitFor(() =>
      expect(screen.getByText(/failed to load captcha/i)).toBeInTheDocument(),
    );
  });

  it('shows validation errors from 422 errors envelope', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() => screen.getByText('What is 2 + 2?'));

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');
    await userEvent.type(screen.getByLabelText(/captcha/i), 'wrong');

    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ errors: { captcha_answer: 'Incorrect answer.' } }),
        { status: 422 },
      ),
    );

    await userEvent.click(screen.getByRole('button', { name: /register/i }));

    await waitFor(() =>
      expect(screen.getByText(/incorrect answer/i)).toBeInTheDocument(),
    );
  });

  it('shows generic error on unexpected status', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() => screen.getByText('What is 2 + 2?'));

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');
    await userEvent.type(screen.getByLabelText(/captcha/i), '4');

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Server error' }), { status: 500 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /register/i }));

    await waitFor(() =>
      expect(screen.getByText(/unexpected error/i)).toBeInTheDocument(),
    );
  });

  it('shows duplicate-field error on 409', async () => {
    mockFetch.mockResolvedValueOnce(captchaResponse());
    renderPage();
    await waitFor(() => screen.getByText('What is 2 + 2?'));

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');
    await userEvent.type(screen.getByLabelText(/captcha/i), '4');

    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ field: 'username', error: 'Username already taken.' }),
        { status: 409 },
      ),
    );

    await userEvent.click(screen.getByRole('button', { name: /register/i }));

    await waitFor(() =>
      expect(screen.getByText(/username already taken/i)).toBeInTheDocument(),
    );
  });
});
