import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import DocumentWizard from './components/DocumentWizard';
import AuthenticatedLayout from './Layouts/AuthenticatedLayout';


// Inicializa o app React
if (document.getElementById('app')) {
    const root = createRoot(document.getElementById('app'));
    root.render(
        <React.StrictMode>
            <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Gerador
                </h2>
            }
            >
                <DocumentWizard />
            </AuthenticatedLayout>
        </React.StrictMode>
    );
}