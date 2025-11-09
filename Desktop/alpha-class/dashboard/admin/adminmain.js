// Configuration - Updated path
const API_BASE = 'admin/api.php';

// Load reference codes on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, attempting to load reference codes...');
    loadReferenceCodes();
    
    // Form submission
    document.getElementById('referenceForm').addEventListener('submit', handleFormSubmit);
    
    // Cancel button
    document.getElementById('cancelBtn').addEventListener('click', resetForm);
    
    // Modal close
    const closeBtn = document.querySelector('.close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('subjectModal');
        if (e.target === modal) {
            closeModal();
        }
    });
});

// Load all reference codes
function loadReferenceCodes() {
    console.log('Fetching from:', `${API_BASE}?action=get_references`);
    
    fetch(`${API_BASE}?action=get_references`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.text(); // Get as text first to see what we're getting
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    displayReferenceCodes(data.data);
                } else {
                    showMessage(data.message || 'Unknown error occurred', 'error');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showMessage('Server error: ' + text.substring(0, 100), 'error');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            showMessage('Network error: ' + error.message, 'error');
        });
}

// Display reference codes in table
function displayReferenceCodes(references) {
    const tbody = document.getElementById('referenceTableBody');
    tbody.innerHTML = '';
    
    if (!references || references.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No reference codes found</td></tr>';
        return;
    }
    
    references.forEach(ref => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${ref.referencecode}</td>
            <td>${ref.class}</td>
            <td>${ref.department}</td>
            <td>${ref.class_teacher}</td>
            <td>${ref.total_strength}</td>
            <td>${ref.total_subjects_in_all_sem}</td>
            <td>${ref.currentSem}</td>
            <td>${ref.starting_year} - ${ref.ending_year}</td>
            <td>
                <button class="btn btn-subjects" onclick="manageSubjects('${ref.referencecode}', ${ref.currentSem})">Subjects</button>
                <button class="btn btn-edit" onclick="editReference(${ref.id})">Edit</button>
                <button class="btn btn-delete" onclick="deleteReference(${ref.id}, '${ref.referencecode}')">Delete</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// Handle form submission
function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const editId = document.getElementById('editId').value;
    
    const action = editId ? 'update_reference' : 'create_reference';
    formData.append('action', action);
    
    console.log('Submitting form with action:', action);
    
    fetch(API_BASE, {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(text => {
            console.log('Form response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showMessage(data.message, 'success');
                    resetForm();
                    loadReferenceCodes();
                } else {
                    showMessage(data.message || 'Error saving data', 'error');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showMessage('Server error: ' + text.substring(0, 100), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error saving data: ' + error.message, 'error');
        });
}

// Edit reference code
function editReference(id) {
    fetch(`${API_BASE}?action=get_reference&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ref = data.data;
                document.getElementById('editId').value = ref.id;
                document.getElementById('referencecode').value = ref.referencecode;
                document.getElementById('referencecode').readOnly = true;
                document.getElementById('class').value = ref.class;
                document.getElementById('department').value = ref.department;
                document.getElementById('class_teacher').value = ref.class_teacher;
                document.getElementById('total_strength').value = ref.total_strength;
                document.getElementById('total_subjects_in_all_sem').value = ref.total_subjects_in_all_sem;
                document.getElementById('currentSem').value = ref.currentSem;
                document.getElementById('starting_year').value = ref.starting_year;
                document.getElementById('ending_year').value = ref.ending_year;
                
                document.getElementById('submitBtn').textContent = 'Update Reference Code';
                document.getElementById('cancelBtn').style.display = 'inline-block';
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                showMessage(data.message || 'Error loading reference', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading reference: ' + error.message, 'error');
        });
}

// Delete reference code
function deleteReference(id, code) {
    if (!confirm(`Are you sure you want to delete reference code "${code}"? This will also delete all associated subjects.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_reference');
    formData.append('id', id);
    
    fetch(API_BASE, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                loadReferenceCodes();
            } else {
                showMessage(data.message || 'Error deleting reference', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error deleting reference: ' + error.message, 'error');
        });
}

// Manage subjects for a reference code
function manageSubjects(referencecode, totalSemesters) {
    fetch(`${API_BASE}?action=get_subjects&referencecode=${referencecode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySubjectModal(referencecode, totalSemesters, data.data);
            } else {
                showMessage(data.message || 'Error loading subjects', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading subjects: ' + error.message, 'error');
        });
}

// Display subject management modal
function displaySubjectModal(referencecode, totalSemesters, existingSubjects) {
    const modal = document.getElementById('subjectModal');
    const content = document.getElementById('subjectModalContent');
    
    let html = `<h3>Reference Code: ${referencecode}</h3>`;
    html += `<form id="subjectForm" data-refcode="${referencecode}">`;
    
    for (let sem = 1; sem <= totalSemesters; sem++) {
        const semSubjects = existingSubjects.filter(s => s.semester == sem);
        
        html += `
            <div class="semester-section">
                <h3>Semester ${sem}</h3>
                <div id="semester${sem}Subjects">
        `;
        
        if (semSubjects.length > 0) {
            semSubjects.forEach((subject, index) => {
                html += createSubjectInput(sem, subject.subject_name, subject.teaching_teacher, index);
            });
        } else {
            html += createSubjectInput(sem, '', '', 0);
        }
        
        html += `
                </div>
                <button type="button" class="btn add-subject-btn" onclick="addSubjectField(${sem})">+ Add Subject</button>
            </div>
        `;
    }
    
    html += `
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save All Subjects</button>
        </div>
    </form>`;
    
    content.innerHTML = html;
    modal.style.display = 'block';
    
    document.getElementById('subjectForm').addEventListener('submit', handleSubjectSubmit);
}

// Create subject input fields
function createSubjectInput(semester, subjectName = '', teacher = '', index = 0) {
    return `
        <div class="subject-item" id="sem${semester}_subject${index}">
            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="sem${semester}_subject[]" value="${subjectName}" required>
            </div>
            <div class="form-group">
                <label>Teaching Teacher</label>
                <input type="text" name="sem${semester}_teacher[]" value="${teacher}" required>
            </div>
            ${index > 0 ? `<button type="button" class="btn remove-subject-btn" onclick="removeSubjectField('sem${semester}_subject${index}')">Remove</button>` : '<div></div>'}
        </div>
    `;
}

// Add subject field
function addSubjectField(semester) {
    const container = document.getElementById(`semester${semester}Subjects`);
    const index = container.children.length;
    const div = document.createElement('div');
    div.innerHTML = createSubjectInput(semester, '', '', index);
    container.appendChild(div.firstElementChild);
}

// Remove subject field
function removeSubjectField(id) {
    const element = document.getElementById(id);
    if (element) {
        element.remove();
    }
}

// Handle subject form submission
function handleSubjectSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const referencecode = e.target.dataset.refcode;
    
    formData.append('action', 'save_subjects');
    formData.append('referencecode', referencecode);
    
    fetch(API_BASE, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeModal();
            } else {
                showMessage(data.message || 'Error saving subjects', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error saving subjects: ' + error.message, 'error');
        });
}

// Close modal
function closeModal() {
    const modal = document.getElementById('subjectModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Reset form
function resetForm() {
    document.getElementById('referenceForm').reset();
    document.getElementById('editId').value = '';
    document.getElementById('referencecode').readOnly = false;
    document.getElementById('submitBtn').textContent = 'Save Reference Code';
    document.getElementById('cancelBtn').style.display = 'none';
}

// Show message
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.textContent = message;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    } else {
        // Fallback to alert if message div doesn't exist
        alert(message);
    }
    
    console.log(`${type.toUpperCase()}: ${message}`);
}