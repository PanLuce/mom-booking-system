class BookingCalendar {
    constructor() {
        this.container = document.getElementById('lessons-container');
        if (this.container) {
            this.loadLessons();
        }
    }

    async loadLessons() {
        try {
            const response = await fetch(mom_booking.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_available_lessons',
                    nonce: mom_booking.nonce
                })
            });

            const result = await response.json();
            if (result.success) {
                this.renderLessons(result.data);
            } else {
                this.container.innerHTML = '<p>Chyba při načítání lekcí.</p>';
            }
        } catch (error) {
            console.error('Chyba při načítání lekcí:', error);
            this.container.innerHTML = '<p>Chyba při načítání lekcí.</p>';
        }
    }

    renderLessons(lessons) {
        if (!lessons || lessons.length === 0) {
            this.container.innerHTML = '<p>Momentálně nejsou dostupné žádné lekce.</p>';
            return;
        }

        const lessonsHTML = lessons.map(lesson => `
            <div class="lesson-card" data-lesson-id="${lesson.id}">
                <h4>${lesson.title}</h4>
                <p><strong>Datum:</strong> ${this.formatDate(lesson.date_time)}</p>
                <p><strong>Volná místa:</strong> ${lesson.available_spots}/${lesson.max_capacity}</p>
                <button class="book-button" onclick="bookingCalendar.showBookingForm(${lesson.id})">
                    Rezervovat
                </button>
            </div>
        `).join('');

        this.container.innerHTML = lessonsHTML;
    }

    showBookingForm(lessonId) {
        const modal = document.createElement('div');
        modal.className = 'booking-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <span class="close" onclick="this.parentElement.parentElement.remove()">&times;</span>
                <h3>Rezervace lekce</h3>
                <form id="booking-form">
                    <input type="hidden" name="lesson_id" value="${lessonId}">
                    <label>Jméno: <input type="text" name="customer_name" required></label>
                    <label>Email: <input type="email" name="customer_email" required></label>
                    <label>Telefon: <input type="tel" name="customer_phone"></label>
                    <button type="submit">Potvrdit rezervaci</button>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        document.getElementById('booking-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitBooking(new FormData(e.target));
            modal.remove();
        });
    }

    async submitBooking(formData) {
        formData.append('action', 'book_lesson');
        formData.append('nonce', mom_booking.nonce);

        try {
            const response = await fetch(mom_booking.ajax_url, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert('Rezervace byla úspěšně vytvořena!');
                this.loadLessons(); // Refresh the list
            } else {
                alert('Chyba: ' + result.data);
            }
        } catch (error) {
            alert('Došlo k chybě při vytváření rezervace.');
        }
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('cs-CZ');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.bookingCalendar = new BookingCalendar();
});
