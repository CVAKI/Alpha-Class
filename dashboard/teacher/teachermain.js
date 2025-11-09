// Smooth scrolling for sidebar links
document.addEventListener('DOMContentLoaded', function() {
    const sidebarLinks = document.querySelectorAll('.sidebar a[href^="#"]');
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Active link highlighting
    const sections = document.querySelectorAll('.section');
    const observerOptions = {
        threshold: 0.3
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                sidebarLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${id}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }, observerOptions);

    sections.forEach(section => {
        observer.observe(section);
    });

    // Add transition styles for student cards
    const studentCards = document.querySelectorAll('.student-card');
    studentCards.forEach(card => {
        card.style.transition = 'all 0.3s ease';
    });

    // Handle semester edit form submission
    const form = document.getElementById('editSemesterForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            fetch('teacher/update_semester.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Semester updated successfully!');
                    closeModal();
                    location.reload();
                } else {
                    alert('Error updating semester: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating semester:', error);
                alert('An error occurred while updating the semester. Check console for details.');
            });
        });
    }
});

// Edit Semester Modal Functions
function editSemester(referenceCode, currentSem) {
    const modal = document.getElementById('editSemesterModal');
    const refCodeInput = document.getElementById('referenceCode');
    const semInput = document.getElementById('newSemester');
    
    refCodeInput.value = referenceCode;
    semInput.value = currentSem;
    
    modal.style.display = 'block';
}

function closeModal() {
    const modal = document.getElementById('editSemesterModal');
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editSemesterModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};

// Delete Student Function
function deleteStudent(userId, studentName) {
    if (confirm(`Are you sure you want to delete student: ${studentName}?\n\nThis action cannot be undone.`)) {
        fetch('teacher/delete_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Student deleted successfully!');
                
                // Remove student card from DOM with animation
                const studentCard = document.querySelector(`.student-card[data-student-id="${userId}"]`);
                if (studentCard) {
                    studentCard.style.opacity = '0';
                    studentCard.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        studentCard.remove();
                        
                        // Check if no students left
                        const remainingStudents = document.querySelectorAll('.student-card').length;
                        if (remainingStudents === 0) {
                            const studentsGrid = document.querySelector('.students-grid');
                            if (studentsGrid) {
                                studentsGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center;">No students found with your reference code.</p>';
                            }
                        }
                    }, 300);
                }
                
                // Update total strength
                location.reload();
            } else {
                alert('Error deleting student: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the student. Check console for details.');
        });
    }
}

// Create Announcement Function
function createAnnouncement() {
    const announcement = prompt('Enter your announcement:');
    
    if (announcement && announcement.trim() !== '') {
        fetch('teacher/create_announcement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `announcement=${encodeURIComponent(announcement)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Announcement created successfully!');
            } else {
                alert('Error creating announcement: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the announcement. Check console for details.');
        });
    }
}